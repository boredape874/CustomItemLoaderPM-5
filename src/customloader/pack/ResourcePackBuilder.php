<?php

declare(strict_types=1);

namespace customloader\pack;

use customloader\block\properties\CustomBlockProperties;
use customloader\entity\CustomEntityProperties;
use customloader\item\properties\CustomItemProperties;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use ZipArchive;
use function array_merge;
use function count;
use function explode;
use function file_exists;
use function in_array;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function json_encode;
use function method_exists;
use function mkdir;
use function preg_replace;
use function sprintf;
use function str_replace;

final class ResourcePackBuilder{

	/**
	 * Molang conditions for each animation trigger shorthand.
	 * null = unconditional (always plays while attached/equipped).
	 * The insertion order here is the default fallback order; actual order is
	 * determined by the config's `animations:` key order.
	 */
	private const ANIMATION_CONDITIONS = [
		"hold"      => null,
		"wear"      => null,
		"attack"    => "query.is_attacking",
		"use"       => "query.is_using_item",
		"sneak_use" => "query.is_sneaking && query.is_using_item",
		"sneak"     => "query.is_sneaking",
		"sprint"    => "query.is_sprinting",
		"swim"      => "query.is_swimming",
		"jump"      => "!query.is_on_ground && query.vertical_speed > 0",
		"fall"      => "!query.is_on_ground && query.vertical_speed < 0",
		"walk"      => "query.is_on_ground && query.modified_move_speed > 0",
		"idle"      => "query.is_on_ground && !(query.modified_move_speed > 0)",
	];

	private string $rpDir;
	private string $bpDir;

	public function __construct(
		private string $baseDir,
		private string $packName
	){
		$this->rpDir = Path::join($baseDir, "resource_packs", $packName);
		$this->bpDir = Path::join($baseDir, "behavior_packs", $packName);
	}

	public function resourcePackExists() : bool{ return is_dir($this->rpDir); }
	public function behaviorPackExists() : bool{ return is_dir($this->bpDir); }

	/**
	 * Creates the full resource pack + behavior pack directory structure.
	 *
	 * @param string                  $packDescription
	 * @param CustomItemProperties[]  $items
	 * @param CustomBlockProperties[] $blocks
	 * @param CustomEntityProperties[] $entities
	 * @param array<string, mixed>[]  $sounds    Sound definition entries keyed by sound id.
	 *                                            Each entry: ["category" => string, "sounds" => [["name" => string]]]
	 * @param array<string, mixed>[]  $particles  Particle definition entries keyed by namespace id.
	 *                                            Each entry: ["texture" => string] (rest uses defaults)
	 */
	public function create(
		string $packDescription,
		array $items = [],
		array $blocks = [],
		array $entities = [],
		array $sounds = [],
		array $particles = []
	) : void{
		$this->createDirectories();
		$this->writeManifest($this->rpDir, $packDescription, "resources");
		$this->writeManifest($this->bpDir, $packDescription, "data");
		$this->writeItemTextures($items);
		$this->writeBlockTextures($blocks);
		$this->writeLangFile($items, $blocks, $entities);
		$this->writeEntityClientFiles($entities);
		$this->writeBehaviorBlockFiles($blocks);
		$this->writeBehaviorEntityFiles($entities);
		if(count($sounds) > 0){
			$this->writeSoundFiles($sounds);
		}
		if(count($particles) > 0){
			$this->writeParticleFiles($particles);
		}
		if(count($entities) > 0){
			$this->writeAnimationFiles($entities);
		}
		if(count($items) > 0){
			$this->writeItemAttachableFiles($items);
		}
	}

	private function createDirectories() : void{
		$dirs = [
			$this->rpDir,
			Path::join($this->rpDir, "textures", "items"),
			Path::join($this->rpDir, "textures", "blocks"),
			Path::join($this->rpDir, "textures", "entity"),
			Path::join($this->rpDir, "textures", "particles"),
			Path::join($this->rpDir, "models", "entity"),
			Path::join($this->rpDir, "entity"),
			Path::join($this->rpDir, "texts"),
			Path::join($this->rpDir, "sounds"),
			Path::join($this->rpDir, "particles"),
			Path::join($this->rpDir, "animations"),
			Path::join($this->rpDir, "animation_controllers"),
			Path::join($this->rpDir, "attachables"),
			$this->bpDir,
			Path::join($this->bpDir, "blocks"),
			Path::join($this->bpDir, "entities"),
		];
		foreach($dirs as $dir){
			if(!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)){
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}
		}
	}

	private function writeManifest(string $packDir, string $description, string $type) : void{
		$protocolParts = explode(".", ProtocolInfo::MINECRAFT_VERSION_NETWORK);
		$manifest = [
			"format_version" => 2,
			"header" => [
				"description" => $description,
				"name" => $this->packName,
				"uuid" => Uuid::uuid4()->toString(),
				"version" => [0, 0, 1],
				"min_engine_version" => [(int) $protocolParts[0], (int) $protocolParts[1], (int) $protocolParts[2]],
			],
			"modules" => [
				[
					"description" => $description,
					"type" => $type,
					"uuid" => Uuid::uuid4()->toString(),
					"version" => [0, 0, 1],
				],
			],
		];
		$this->writeJson(Path::join($packDir, "manifest.json"), $manifest);
	}

	/** @param CustomItemProperties[] $items */
	private function writeItemTextures(array $items) : void{
		$textureData = [];
		foreach($items as $props){
			$texture = $props->getNamespace();
			$textureName = str_replace(":", "_", $texture);
			$textureData[$textureName] = ["textures" => "textures/items/{$props->getTexture()}"];
		}
		$this->writeJson(Path::join($this->rpDir, "textures", "item_texture.json"), [
			"resource_pack_name" => "vanilla",
			"texture_name" => "atlas.items",
			"texture_data" => $textureData,
		]);
	}

	/** @param CustomBlockProperties[] $blocks */
	private function writeBlockTextures(array $blocks) : void{
		$textureData = [];
		foreach($blocks as $props){
			$textureData[$props->getTexture()] = ["textures" => "textures/blocks/{$props->getTexture()}"];
		}
		$this->writeJson(Path::join($this->rpDir, "textures", "terrain_texture.json"), [
			"resource_pack_name" => "vanilla",
			"texture_name" => "atlas.terrain",
			"texture_data" => $textureData,
		]);
	}

	/**
	 * @param CustomItemProperties[]  $items
	 * @param CustomBlockProperties[] $blocks
	 * @param CustomEntityProperties[] $entities
	 */
	private function writeLangFile(array $items, array $blocks, array $entities) : void{
		$lines = [];
		foreach($items as $props){
			$lines[] = "item.{$props->getNamespace()}={$props->getName()}";
		}
		foreach($blocks as $props){
			$lines[] = "tile.{$props->getNamespace()}.name={$props->getName()}";
		}
		foreach($entities as $props){
			$lines[] = "entity.{$props->getNamespace()}.name={$props->getName()}";
		}
		file_put_contents(Path::join($this->rpDir, "texts", "en_US.lang"), implode("\n", $lines));
	}

	/**
	 * Generates `sounds/sound_definitions.json` from a map of sound ids to their definition.
	 *
	 * Expected $sounds format (keyed by sound id string):
	 * [
	 *   "ruby.break" => ["category" => "block", "sounds" => [["name" => "sounds/ruby_break"]]],
	 *   ...
	 * ]
	 *
	 * @param array<string, mixed> $sounds
	 */
	public function writeSoundFiles(array $sounds) : void{
		$soundDir = Path::join($this->rpDir, "sounds");
		if(!is_dir($soundDir) && !mkdir($soundDir, 0777, true) && !is_dir($soundDir)){
			throw new RuntimeException(sprintf('Directory "%s" was not created', $soundDir));
		}

		$definitions = [];
		foreach($sounds as $soundId => $entry){
			$category = (string) ($entry["category"] ?? "neutral");
			$soundEntries = is_array($entry["sounds"] ?? null) ? $entry["sounds"] : [];
			$definitions[$soundId] = [
				"category" => $category,
				"sounds" => $soundEntries,
			];
		}

		$this->writeJson(Path::join($soundDir, "sound_definitions.json"), [
			"sound_definitions" => $definitions,
		]);
	}

	/**
	 * Generates a `particles/{namespace}.particle.json` file for each particle entry.
	 *
	 * Expected $particles format (keyed by particle namespace id):
	 * [
	 *   "example:ruby_burst" => ["texture" => "textures/particles/ruby_burst"],
	 *   ...
	 * ]
	 *
	 * @param array<string, mixed> $particles
	 */
	public function writeParticleFiles(array $particles) : void{
		$particleDir = Path::join($this->rpDir, "particles");
		if(!is_dir($particleDir) && !mkdir($particleDir, 0777, true) && !is_dir($particleDir)){
			throw new RuntimeException(sprintf('Directory "%s" was not created', $particleDir));
		}

		foreach($particles as $namespaceId => $entry){
			$safeId = str_replace(":", "_", $namespaceId);
			$texture = (string) ($entry["texture"] ?? "textures/particles/{$safeId}");

			$def = [
				"format_version" => "1.10.0",
				"particle_effect" => [
					"description" => [
						"identifier" => $namespaceId,
						"basic_render_parameters" => [
							"material" => "particles_alpha",
							"texture" => $texture,
						],
					],
					"components" => [
						"minecraft:emitter_rate_instant" => [
							"num_particles" => (int) ($entry["amount"] ?? 10),
						],
						"minecraft:emitter_lifetime_once" => [
							"active_time" => 0,
						],
						"minecraft:emitter_shape_point" => new \stdClass(),
						"minecraft:particle_initial_speed" => (float) ($entry["speed"] ?? 5.0),
						"minecraft:particle_lifetime_expression" => [
							"max_lifetime" => (float) ($entry["lifetime"] ?? 1.0),
						],
					],
				],
			];

			$this->writeJson(Path::join($particleDir, "{$safeId}.particle.json"), $def);
		}
	}

	/**
	 * Generates animation and animation controller JSON files for any entity that
	 * has animations defined.
	 *
	 * Calls $props->getAnimations() and $props->getAnimateBehavior() — these methods
	 * must exist on CustomEntityProperties (added separately).
	 *
	 * getAnimations() returns ?array in the form:
	 *   ["walk" => "animation.my_mob.walk", "attack" => "animation.my_mob.attack"]
	 *
	 * getAnimateBehavior() returns ?array in the form:
	 *   [["walk" => "query.modified_move_speed > 0"], ["attack" => "query.is_attacking"]]
	 *
	 * @param CustomEntityProperties[] $entities
	 */
	public function writeAnimationFiles(array $entities) : void{
		$animDir = Path::join($this->rpDir, "animations");
		$controllerDir = Path::join($this->rpDir, "animation_controllers");

		foreach([$animDir, $controllerDir] as $dir){
			if(!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)){
				throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
			}
		}

		foreach($entities as $props){
			// getAnimations() is a new method — guard with method_exists for safety.
			if(!method_exists($props, 'getAnimations')){
				continue;
			}
			/** @var ?array<string, string> $animations */
			$animations = $props->getAnimations();
			if($animations === null || count($animations) === 0){
				continue;
			}

			$safeId = str_replace(":", "_", $props->getNamespace());
			$namespace = $props->getNamespace();

			// --- animations/{safeId}.animation.json ---
			$animEntries = [];
			foreach($animations as $shortName => $animId){
				$animEntries[$animId] = [
					"loop" => true,
					"bones" => new \stdClass(),
				];
			}
			$this->writeJson(Path::join($animDir, "{$safeId}.animation.json"), [
				"format_version" => "1.8.0",
				"animations" => $animEntries,
			]);

			// --- animation_controllers/{safeId}.animation_controller.json ---
			// getAnimateBehavior() returns the animate array for the controller.
			$animateBehavior = method_exists($props, 'getAnimateBehavior')
				? $props->getAnimateBehavior()
				: null;

			// Build the states array: initial_state -> animations list.
			$stateAnimations = [];
			foreach($animations as $shortName => $animId){
				$stateAnimations[] = $shortName;
			}

			$controllerDef = [
				"format_version" => "1.10.0",
				"animation_controllers" => [
					"controller.animation.{$safeId}.default" => [
						"initial_state" => "default",
						"states" => [
							"default" => [
								"animations" => $stateAnimations,
							],
						],
					],
				],
			];
			$this->writeJson(Path::join($controllerDir, "{$safeId}.animation_controller.json"), $controllerDef);
		}
	}

	/** @param CustomEntityProperties[] $entities */
	private function writeEntityClientFiles(array $entities) : void{
		foreach($entities as $props){
			$safeId = str_replace(":", "_", $props->getNamespace());
			$geometryId = $props->getModel() ?? "geometry.{$safeId}";

			$description = [
				"identifier" => $props->getNamespace(),
				"materials" => ["default" => "entity_alphatest"],
				"textures" => ["default" => "textures/entity/{$props->getTexture()}"],
				"geometry" => ["default" => $geometryId],
				"render_controllers" => ["controller.render.entity_alphatest"],
			];

			// Inject animations block if the entity has animation data.
			if(method_exists($props, 'getAnimations')){
				/** @var ?array<string, string> $animations */
				$animations = $props->getAnimations();
				if($animations !== null && count($animations) > 0){
					$animBlock = [];
					foreach($animations as $shortName => $animId){
						$animBlock[$shortName] = $animId;
					}
					// Add controller reference into the animations map.
					$animBlock["controller.default"] = "controller.animation.{$safeId}.default";
					$description["animations"] = $animBlock;

					// Build scripts.animate array.
					$animateArray = [];
					if(method_exists($props, 'getAnimateBehavior') && is_array($props->getAnimateBehavior())){
						foreach($props->getAnimateBehavior() as $behaviorEntry){
							$animateArray[] = $behaviorEntry;
						}
					} else {
						// Default: play all animations unconditionally.
						foreach($animations as $shortName => $animId){
							$animateArray[] = $shortName;
						}
					}
					$animateArray[] = "controller.default";
					$description["scripts"] = ["animate" => $animateArray];
				}
			}

			$def = [
				"format_version" => "1.10.0",
				"minecraft:client_entity" => [
					"description" => $description,
				],
			];
			$this->writeJson(Path::join($this->rpDir, "entity", "{$safeId}.entity.json"), $def);
		}
	}

	/** @param CustomBlockProperties[] $blocks */
	private function writeBehaviorBlockFiles(array $blocks) : void{
		foreach($blocks as $props){
			$safeId = str_replace(":", "_", $props->getNamespace());
			$components = [
				"minecraft:material_instances" => [
					"*" => ["texture" => $props->getTexture(), "render_method" => "opaque"],
				],
				"minecraft:light_emission" => ["emission" => $props->getLightEmission()],
			];
			if($props->getModel() !== null){
				$components["minecraft:geometry"] = ["identifier" => $props->getModel()];
			}
			$def = [
				"format_version" => "1.20.80",
				"minecraft:block" => [
					"description" => ["identifier" => $props->getNamespace()],
					"components" => $components,
				],
			];
			$this->writeJson(Path::join($this->bpDir, "blocks", "{$safeId}.json"), $def);
		}
	}

	/** @param CustomEntityProperties[] $entities */
	private function writeBehaviorEntityFiles(array $entities) : void{
		foreach($entities as $props){
			$safeId = str_replace(":", "_", $props->getNamespace());
			$def = [
				"format_version" => "1.20.80",
				"minecraft:entity" => [
					"description" => [
						"identifier" => $props->getNamespace(),
						"is_spawnable" => true,
						"is_summonable" => true,
						"is_experimental" => false,
					],
					"component_groups" => new \stdClass(),
					"components" => [
						"minecraft:type_family" => ["family" => ["mob", "custom"]],
						"minecraft:physics" => new \stdClass(),
						"minecraft:pushable" => ["is_pushable" => true, "is_pushable_by_piston" => true],
						"minecraft:health" => ["value" => (int) $props->getMaxHealth(), "max" => (int) $props->getMaxHealth()],
						"minecraft:movement" => ["value" => $props->getMovementSpeed()],
						"minecraft:collision_box" => ["width" => $props->getWidth(), "height" => $props->getHeight()],
					],
					"events" => new \stdClass(),
				],
			];
			$this->writeJson(Path::join($this->bpDir, "entities", "{$safeId}.json"), $def);
		}
	}

	/**
	 * Zips the resource pack and behavior pack into .mcpack files.
	 * Returns paths to the created .mcpack files.
	 *
	 * @return string[]
	 */
	public function buildMcpacks() : array{
		$created = [];
		if(is_dir($this->rpDir)){
			$rpPath = $this->rpDir . "_rp.mcpack";
			$this->zipDirectory($this->rpDir, $rpPath);
			$created[] = $rpPath;
		}
		if(is_dir($this->bpDir)){
			$bpPath = $this->bpDir . "_bp.mcpack";
			$this->zipDirectory($this->bpDir, $bpPath);
			$created[] = $bpPath;
		}
		return $created;
	}

	private function zipDirectory(string $sourceDir, string $outputPath) : void{
		$zip = new ZipArchive();
		$zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		/** @var SplFileInfo $file */
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir)) as $file){
			if($file->isFile()){
				$packName = $this->packName;
				$relativePath = (string) preg_replace("/.*[\/\\\\]{$packName}[\/\\\\].*/U", '', $file->getPathname());
				$relativePath = str_replace("\\", "/", $relativePath);
				$zip->addFile($file->getPathname(), ltrim($relativePath, "/"));
			}
		}
		$zip->close();
	}

	/**
	 * 아이템에 animations 맵이 있으면 Bedrock attachable JSON + 애니메이션 스텁을 자동 생성.
	 * 애니메이션 재생 순서는 config의 animations: 키 순서와 동일 (PHP 배열 삽입 순서 보존).
	 *
	 * 생성 파일:
	 *   attachables/{safeId}.json           — 클라이언트 attachable 정의
	 *   animations/{safeId}.animation.json  — 애니메이션 스텁 (미존재 시만 생성)
	 *
	 * @param CustomItemProperties[] $items
	 */
	public function writeItemAttachableFiles(array $items) : void{
		$attachableDir = Path::join($this->rpDir, "attachables");
		$animDir       = Path::join($this->rpDir, "animations");

		foreach($items as $props){
			$animations = $props->getAnimations();
			if(count($animations) === 0){
				continue;
			}

			$ns     = $props->getNamespace();
			$safeId = str_replace(":", "_", $ns);

			if($props->isArmor()){
				$this->writeArmorAttachable($attachableDir, $animDir, $ns, $safeId, $props, $animations);
			} else {
				$this->writeRegularAttachable($attachableDir, $animDir, $ns, $safeId, $props, $animations);
			}
		}
	}

	/**
	 * 일반 아이템 attachable JSON + 애니메이션 스텁 생성.
	 * $animations 키 순서 = Bedrock scripts.animate 순서.
	 *
	 * @param array<string, string> $animations
	 */
	private function writeRegularAttachable(
		string $attachableDir,
		string $animDir,
		string $ns,
		string $safeId,
		CustomItemProperties $props,
		array $animations
	) : void{
		$scriptAnimate = $this->buildScriptAnimate($animations);

		$attachable = [
			"format_version"      => "1.10.0",
			"minecraft:attachable" => [
				"description" => [
					"identifier"         => $ns,
					"materials"          => ["default" => "entity_alphatest_glint"],
					"textures"           => ["default" => "textures/items/{$props->getTexture()}"],
					"geometry"           => ["default" => "geometry.humanoid.handheld"],
					"animations"         => $animations,
					"scripts"            => ["animate" => $scriptAnimate],
					"render_controllers" => ["controller.render.item_default"],
				],
			],
		];
		$this->writeJson(Path::join($attachableDir, "{$safeId}.json"), $attachable);

		// animation stub — 기존 파일 덮어쓰지 않음
		$animPath = Path::join($animDir, "{$safeId}.animation.json");
		if(!file_exists($animPath)){
			$loopedTriggers = ["hold", "wear", "walk", "idle", "swim", "sneak", "sprint"];
			$animEntries = [];
			foreach($animations as $shortName => $animId){
				$animEntries[$animId] = [
					"loop"             => in_array($shortName, $loopedTriggers, true),
					"animation_length" => 1.0,
					"bones"            => new \stdClass(),
				];
			}
			$this->writeJson($animPath, [
				"format_version" => "1.8.0",
				"animations"     => $animEntries,
			]);
		}
	}

	/**
	 * 방어구 attachable JSON + 애니메이션 스텁 생성.
	 * armor_slot에 따른 humanoid.armor geometry + armor 재질 사용.
	 * $animations 키 순서 = Bedrock scripts.animate 순서.
	 *
	 * @param array<string, string> $animations
	 */
	private function writeArmorAttachable(
		string $attachableDir,
		string $animDir,
		string $ns,
		string $safeId,
		CustomItemProperties $props,
		array $animations
	) : void{
		$geometry = match($props->getArmorSlot()){
			0 => "geometry.humanoid.armor.helmet",
			1 => "geometry.humanoid.armor.chestplate",
			2 => "geometry.humanoid.armor.leggings",
			3 => "geometry.humanoid.armor.boots",
			default => "geometry.humanoid.armor.chestplate",
		};

		$scriptAnimate = $this->buildScriptAnimate($animations);

		$attachable = [
			"format_version"      => "1.10.0",
			"minecraft:attachable" => [
				"description" => [
					"identifier"         => $ns,
					"materials"          => ["default" => "armor", "enchanted" => "armor_enchanted"],
					"textures"           => [
						"default"   => "textures/items/{$props->getTexture()}",
						"enchanted" => "textures/misc/enchanted_item_glint",
					],
					"geometry"           => ["default" => $geometry],
					"animations"         => $animations,
					"scripts"            => ["animate" => $scriptAnimate],
					"render_controllers" => ["controller.render.armor"],
				],
			],
		];
		$this->writeJson(Path::join($attachableDir, "{$safeId}.json"), $attachable);

		$animPath = Path::join($animDir, "{$safeId}.animation.json");
		if(!file_exists($animPath)){
			$animEntries = [];
			foreach($animations as $animId){
				$animEntries[$animId] = [
					"loop"             => true,
					"animation_length" => 1.0,
					"bones"            => new \stdClass(),
				];
			}
			$this->writeJson($animPath, [
				"format_version" => "1.8.0",
				"animations"     => $animEntries,
			]);
		}
	}

	/**
	 * animations 맵에서 Bedrock scripts.animate 배열을 생성.
	 * 배열 순서 = $animations 키 삽입 순서 (= config에 적힌 순서).
	 * ANIMATION_CONDITIONS에 없는 키는 unconditional로 처리.
	 *
	 * @param  array<string, string>        $animations
	 * @return array<int, string|array<string, string>>
	 */
	private function buildScriptAnimate(array $animations) : array{
		$scriptAnimate = [];
		foreach($animations as $shortName => $animId){
			$condition = self::ANIMATION_CONDITIONS[$shortName] ?? null;
			if($condition === null){
				$scriptAnimate[] = $shortName;
			} else {
				$scriptAnimate[] = [$shortName => $condition];
			}
		}
		return $scriptAnimate;
	}

	/**
	 * Adds a single item texture entry to an existing pack.
	 */
	public function addItemEntry(string $name, string $namespace, string $texture) : void{
		$path = Path::join($this->rpDir, "textures", "item_texture.json");
		$data = $this->readJson($path) ?? [
			"resource_pack_name" => "vanilla",
			"texture_name" => "atlas.items",
			"texture_data" => [],
		];
		$data["texture_data"][$name] = ["textures" => "textures/items/{$texture}"];
		$this->writeJson($path, $data);

		$langPath = Path::join($this->rpDir, "texts", "en_US.lang");
		$existing = file_get_contents($langPath);
		file_put_contents($langPath, $existing . "\nitem.{$namespace}={$name}");
	}

	private function writeJson(string $path, mixed $data) : void{
		file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE));
	}

	private function readJson(string $path) : ?array{
		if(!file_exists($path)){
			return null;
		}
		$content = file_get_contents($path);
		if($content === false){
			return null;
		}
		return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
	}

	public function getRpDir() : string{ return $this->rpDir; }
	public function getBpDir() : string{ return $this->bpDir; }
}
