<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\entity\CustomEntity;
use customloader\entity\CustomEntityProperties;
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

	public function registerDefaultEntities(array $data) : void{
		if(count($data) === 0){
			return;
		}
		$this->ensureFactoryRegistered();
		foreach($data as $name => $entityData){
			$this->registerEntity((string) $name, (array) $entityData);
		}
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
