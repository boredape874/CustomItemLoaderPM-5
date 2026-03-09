<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\entity\CustomEntity;
use customloader\entity\CustomEntityProperties;
use customloader\entity\spawn\SpawnRule;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use Throwable;

final class CustomEntityManager{
	use SingletonTrait;

	/** @var CustomEntityProperties[] keyed by namespace */
	private array $registeredEntities = [];
	private bool $factoryRegistered = false;

	public function __construct(){}

	private function ensureFactoryRegistered() : void{
		if($this->factoryRegistered){
			return;
		}
		$this->factoryRegistered = true;
		EntityFactory::getInstance()->register(
			CustomEntity::class,
			function(World $world, CompoundTag $nbt) : CustomEntity{
				return new CustomEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
			},
			["customloader:entity", "CustomEntity"]
		);
	}

	/**
	 * Validates config data without registering anything.
	 * Returns an array of [name => errorMessage] for invalid entries.
	 *
	 * @return array<string, string>
	 */
	public function validateConfig(array $data) : array{
		$errors = [];
		foreach($data as $name => $entityData){
			try{
				new CustomEntityProperties((string) $name, (array) $entityData);
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
	}

	/**
	 * Registers all entities from config data.
	 * Returns an array of [name => errorMessage] for failed entities.
	 *
	 * @return array<string, string>
	 */
	public function registerDefaultEntities(array $data) : array{
		$errors = [];
		if(count($data) === 0){
			return $errors;
		}
		$this->ensureFactoryRegistered();
		foreach($data as $name => $entityData){
			try{
				$this->registerEntity((string) $name, (array) $entityData);
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
	}

	public function registerEntity(string $name, array $data) : void{
		try{
			$this->ensureFactoryRegistered();
			$props = new CustomEntityProperties($name, $data);
			$this->registeredEntities[$props->getNamespace()] = $props;
			CustomEntity::registerType($props);
		}catch(Throwable $e){
			throw new \InvalidArgumentException("Failed to register entity '$name': " . $e->getMessage(), 0, $e);
		}
	}

	public function getPropertiesByNamespace(string $namespace) : ?CustomEntityProperties{
		return $this->registeredEntities[$namespace] ?? null;
	}

	/** @return CustomEntityProperties[] */
	public function getRegisteredEntities() : array{
		return $this->registeredEntities;
	}

	/**
	 * Builds and returns a SpawnRule for every registered entity that has spawn data.
	 * Entities without a "spawn" config section are skipped.
	 *
	 * @return SpawnRule[]
	 */
	public function getSpawnRules() : array{
		$rules = [];
		foreach($this->registeredEntities as $props){
			$spawnData = $props->getSpawnData();
			if($spawnData === null){
				continue;
			}
			$rules[] = new SpawnRule($props->getNamespace(), $spawnData);
		}
		return $rules;
	}

	/**
	 * Spawns a custom entity at the given location.
	 * The entity type must have been registered first via registerEntity().
	 */
	public function spawnEntity(\pocketmine\world\Location $location, string $namespace) : ?CustomEntity{
		if(!isset($this->registeredEntities[$namespace])){
			return null;
		}
		$nbt = CompoundTag::create()->setString("CustomType", $namespace);
		$entity = new CustomEntity($location, $nbt);
		$entity->spawnToAll();
		return $entity;
	}
}
