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
use function explode;
use function file_put_contents;
use function implode;
use function is_dir;
use function json_encode;
use function mkdir;
use function preg_replace;
use function sprintf;
use function str_replace;

final class ResourcePackBuilder{

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
	 * @param string $packDescription
	 * @param CustomItemProperties[] $items
	 * @param CustomBlockProperties[] $blocks
	 * @param CustomEntityProperties[] $entities
	 */
	public function create(
		string $packDescription,
		array $items = [],
		array $blocks = [],
		array $entities = []
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
	}

	private function createDirectories() : void{
		$dirs = [
			$this->rpDir,
			Path::join($this->rpDir, "textures", "items"),
			Path::join($this->rpDir, "textures", "blocks"),
			Path::join($this->rpDir, "textures", "entity"),
			Path::join($this->rpDir, "models", "entity"),
			Path::join($this->rpDir, "entity"),
			Path::join($this->rpDir, "texts"),
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
	 * @param CustomItemProperties[] $items
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

	/** @param CustomEntityProperties[] $entities */
	private function writeEntityClientFiles(array $entities) : void{
		foreach($entities as $props){
			$safeId = str_replace(":", "_", $props->getNamespace());
			$geometryId = $props->getModel() ?? "geometry.{$safeId}";
			$def = [
				"format_version" => "1.10.0",
				"minecraft:client_entity" => [
					"description" => [
						"identifier" => $props->getNamespace(),
						"materials" => ["default" => "entity_alphatest"],
						"textures" => ["default" => "textures/entity/{$props->getTexture()}"],
						"geometry" => ["default" => $geometryId],
						"render_controllers" => ["controller.render.entity_alphatest"],
					],
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
