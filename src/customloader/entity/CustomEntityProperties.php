<?php

declare(strict_types=1);

namespace customloader\entity;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function is_array;
use function lcg_value;
use function mt_rand;

final class CustomEntityProperties{

	private string $namespace;
	private string $texture;
	private ?string $model;
	private float $width;
	private float $height;
	private float $maxHealth;
	private float $attackDamage;
	private float $movementSpeed;
	private float $followRange;
	/** @var array<array{item: Item, count_min: int, count_max: int, chance: float}> */
	private array $drops = [];
	/** @var array<array<string, mixed>> */
	private array $goalDefinitions = [];
	/** @var array<string, mixed>|null  Raw spawn config (passed to SpawnRule) */
	private ?array $spawnData = null;
	/** @var array<string, string>|null  Animation identifier → animation key mapping */
	private ?array $animations = null;
	/** @var array<string, mixed>|null  animate.states / animate list */
	private ?array $animateBehavior = null;

	public function __construct(private string $name, array $data){
		$this->parseData($data);
	}

	private function parseData(array $data) : void{
		if(!isset($data["namespace"])){
			throw new InvalidArgumentException("namespace is required");
		}
		if(!isset($data["texture"])){
			throw new InvalidArgumentException("texture is required");
		}

		$this->namespace = (string) $data["namespace"];
		$this->texture = (string) $data["texture"];
		$this->model = isset($data["model"]) ? (string) $data["model"] : null;
		$this->width = isset($data["width"]) ? (float) $data["width"] : 0.6;
		$this->height = isset($data["height"]) ? (float) $data["height"] : 1.8;
		$this->maxHealth = isset($data["max_health"]) ? (float) $data["max_health"] : 20.0;
		$this->attackDamage = isset($data["attack_damage"]) ? (float) $data["attack_damage"] : 2.0;
		$this->movementSpeed = isset($data["movement_speed"]) ? (float) $data["movement_speed"] : 0.25;
		$this->followRange = isset($data["follow_range"]) ? (float) $data["follow_range"] : 16.0;

		if(isset($data["drops"]) && is_array($data["drops"])){
			foreach($data["drops"] as $drop){
				if(!isset($drop["id"])) continue;
				$item = StringToItemParser::getInstance()->parse((string) $drop["id"]);
				if($item === null) continue;
				$this->drops[] = [
					"item" => $item,
					"count_min" => (int) ($drop["count_min"] ?? $drop["count"] ?? 1),
					"count_max" => (int) ($drop["count_max"] ?? $drop["count"] ?? 1),
					"chance" => (float) ($drop["chance"] ?? 1.0),
				];
			}
		}

		if(isset($data["goals"]) && is_array($data["goals"])){
			$this->goalDefinitions = $data["goals"];
		}

		// Optional spawn rules (passed through to SpawnRule constructor)
		if(isset($data["spawn"]) && is_array($data["spawn"])){
			$this->spawnData = $data["spawn"];
		}

		// Optional animation bindings for ResourcePackBuilder
		// animations:
		//   walk: "animation.my_mob.walk"
		//   attack: "animation.my_mob.attack"
		if(isset($data["animations"]) && is_array($data["animations"])){
			$parsed = [];
			foreach($data["animations"] as $key => $animId){
				$parsed[(string) $key] = (string) $animId;
			}
			$this->animations = $parsed;
		}

		// Optional animate controller states
		// animate:
		//   - walk
		//   - { attack: "query.is_attacking" }
		if(isset($data["animate"]) && is_array($data["animate"])){
			$this->animateBehavior = $data["animate"];
		}
	}

	public function getName() : string{ return $this->name; }
	public function getNamespace() : string{ return $this->namespace; }
	public function getTexture() : string{ return $this->texture; }
	public function getModel() : ?string{ return $this->model; }
	public function getWidth() : float{ return $this->width; }
	public function getHeight() : float{ return $this->height; }
	public function getMaxHealth() : float{ return $this->maxHealth; }
	public function getAttackDamage() : float{ return $this->attackDamage; }
	public function getMovementSpeed() : float{ return $this->movementSpeed; }
	public function getFollowRange() : float{ return $this->followRange; }
	public function hasDrops() : bool{ return count($this->drops) > 0; }

	/** @return array<array<string, mixed>> */
	public function getGoalDefinitions() : array{ return $this->goalDefinitions; }

	/** @return array<string, mixed>|null  Raw spawn config, or null if no spawn rules defined */
	public function getSpawnData() : ?array{ return $this->spawnData; }

	/** @return array<string, string>|null  Animation key → animation identifier, or null */
	public function getAnimations() : ?array{ return $this->animations; }

	/** @return array<string, mixed>|null  Animate controller states list, or null */
	public function getAnimateBehavior() : ?array{ return $this->animateBehavior; }

	/** @return Item[] */
	public function getDropItems() : array{
		$result = [];
		foreach($this->drops as $drop){
			if(lcg_value() <= $drop["chance"]){
				$item = clone $drop["item"];
				$item->setCount(mt_rand($drop["count_min"], $drop["count_max"]));
				$result[] = $item;
			}
		}
		return $result;
	}
}
