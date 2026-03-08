<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use pocketmine\entity\Living;

/**
 * Makes the entity retaliate against whoever last hurt it.
 */
final class HurtByTargetGoal implements Goal{

	private ?Living $damager = null;

	public function __construct(private CustomEntity $entity){}

	public function canUse() : bool{
		$lastDamager = $this->entity->getLastDamager();
		if($lastDamager === null || !$lastDamager->isAlive() || $lastDamager->isClosed()){
			return false;
		}
		$this->damager = $lastDamager;
		return true;
	}

	public function canContinueToUse() : bool{
		$target = $this->entity->getTargetEntity();
		return $target !== null && $target->isAlive() && !$target->isClosed();
	}

	public function start() : void{
		$this->entity->setTargetEntity($this->damager);
		$this->entity->clearLastDamager();
	}

	public function tick() : void{}

	public function stop() : void{
		$this->damager = null;
	}

	public function isInterruptable() : bool{
		return true;
	}
}
