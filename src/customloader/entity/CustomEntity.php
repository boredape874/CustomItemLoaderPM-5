<?php

declare(strict_types=1);

namespace customloader\entity;

use customloader\entity\ai\goal\FloatGoal;
use customloader\entity\ai\goal\Goal;
use customloader\entity\ai\goal\GoalSelector;
use customloader\entity\ai\goal\HurtByTargetGoal;
use customloader\entity\ai\goal\LookAtEntityGoal;
use customloader\entity\ai\goal\MeleeAttackGoal;
use customloader\entity\ai\goal\NearestAttackableGoal;
use customloader\entity\ai\goal\RandomStrollGoal;
use customloader\entity\ai\navigation\PathNavigation;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;

class CustomEntity extends Living{

	/** @var CustomEntityProperties[] keyed by namespace */
	private static array $typeRegistry = [];

	/** Stored before parent::__construct so getInitialSizeInfo() can read it */
	private ?CompoundTag $_initNbt = null;

	private ?CustomEntityProperties $properties = null;
	private ?Living $targetEntity = null;
	private ?Living $lastDamager = null;

	private GoalSelector $goalSelector;
	private PathNavigation $navigation;

	public static function registerType(CustomEntityProperties $props) : void{
		self::$typeRegistry[$props->getNamespace()] = $props;
	}

	public function __construct(\pocketmine\world\Location $location, ?CompoundTag $nbt = null){
		$this->_initNbt = $nbt;
		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		$entityType = $this->_initNbt?->getString("CustomType", "") ?? "";
		if($entityType !== "" && isset(self::$typeRegistry[$entityType])){
			$props = self::$typeRegistry[$entityType];
			return new EntitySizeInfo($props->getHeight(), $props->getWidth());
		}
		return new EntitySizeInfo(1.8, 0.6);
	}

	public static function getNetworkTypeId() : string{
		// All custom entities appear as zombies server-side.
		// Client-side appearance is overridden by the generated behavior/resource pack.
		return "minecraft:zombie";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$entityType = $nbt->getString("CustomType", "");
		if($entityType !== "" && isset(self::$typeRegistry[$entityType])){
			$this->properties = self::$typeRegistry[$entityType];
			$this->setMaxHealth((int) $this->properties->getMaxHealth());
			$this->setHealth($this->getMaxHealth());
		}

		$this->goalSelector = new GoalSelector();
		$this->navigation = new PathNavigation($this);

		if($this->properties !== null){
			foreach($this->properties->getGoalDefinitions() as $goalDef){
				$goal = $this->createGoal($goalDef);
				if($goal !== null){
					$this->goalSelector->addGoal((int) ($goalDef["priority"] ?? 5), $goal);
				}
			}
		}
	}

	private function createGoal(array $goalDef) : ?Goal{
		return match($goalDef["type"] ?? ""){
			"float" => new FloatGoal($this),
			"random_stroll" => new RandomStrollGoal($this, $this->navigation, (float) ($goalDef["speed_modifier"] ?? 1.0)),
			"melee_attack" => new MeleeAttackGoal($this, $this->navigation, (float) ($goalDef["speed_modifier"] ?? 1.0)),
			"look_at_entity" => new LookAtEntityGoal($this, (float) ($goalDef["look_distance"] ?? 8.0)),
			"hurt_by_target" => new HurtByTargetGoal($this),
			"nearest_attackable" => new NearestAttackableGoal($this, (float) ($goalDef["distance"] ?? 16.0), (string) ($goalDef["target"] ?? "player")),
			default => null,
		};
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if($this->properties !== null){
			$nbt->setString("CustomType", $this->properties->getNamespace());
		}
		return $nbt;
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->isClosed()){
			return false;
		}
		$hasUpdate = parent::onUpdate($currentTick);

		if($this->isAlive()){
			$this->navigation->tick();
			if($currentTick % 2 === 0){
				$this->goalSelector->tick();
			}
		}

		return $hasUpdate;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled() && $source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living){
				$this->lastDamager = $damager;
			}
		}
	}

	protected function onDeath() : void{
		parent::onDeath();
		$this->goalSelector->stopAll();
		$this->navigation->stop();
	}

	protected function getDrops() : array{
		if($this->properties !== null && $this->properties->hasDrops()){
			return $this->properties->getDropItems();
		}
		return [];
	}

	// --- Public accessors for AI goals ---

	public function getProperties() : ?CustomEntityProperties{
		return $this->properties;
	}

	public function getTargetEntity() : ?Living{
		if($this->targetEntity !== null && ($this->targetEntity->isClosed() || !$this->targetEntity->isAlive())){
			$this->targetEntity = null;
		}
		return $this->targetEntity;
	}

	public function setTargetEntity(?Living $entity) : void{
		$this->targetEntity = $entity;
	}

	public function getLastDamager() : ?Living{
		if($this->lastDamager !== null && ($this->lastDamager->isClosed() || !$this->lastDamager->isAlive())){
			$this->lastDamager = null;
		}
		return $this->lastDamager;
	}

	public function clearLastDamager() : void{
		$this->lastDamager = null;
	}

	public function setYaw(float $yaw) : void{
		$this->location->yaw = $yaw;
	}

	public function setPitch(float $pitch) : void{
		$this->location->pitch = $pitch;
	}
}
