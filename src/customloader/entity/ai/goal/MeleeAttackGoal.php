<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use customloader\entity\ai\navigation\PathNavigation;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

/**
 * Makes the entity move toward and attack its current target in melee range.
 */
final class MeleeAttackGoal implements Goal{

	private int $attackCooldown = 0;
	private float $speedModifier;

	public function __construct(
		private CustomEntity $entity,
		private PathNavigation $navigation,
		float $speedModifier = 1.0
	){
		$this->speedModifier = $speedModifier;
	}

	public function canUse() : bool{
		$target = $this->entity->getTargetEntity();
		return $target instanceof Living && $target->isAlive() && !$target->isClosed();
	}

	public function canContinueToUse() : bool{
		return $this->canUse();
	}

	public function start() : void{
		$this->attackCooldown = 0;
	}

	public function tick() : void{
		$target = $this->entity->getTargetEntity();
		if(!$target instanceof Living || !$target->isAlive()){
			$this->entity->setTargetEntity(null);
			return;
		}

		$this->navigation->moveTo($target->getPosition(), $this->speedModifier);

		$dist = $this->entity->getPosition()->distance($target->getPosition());
		if($dist <= 2.0 && --$this->attackCooldown <= 0){
			$this->attackCooldown = 20;
			$ev = new EntityDamageByEntityEvent(
				$this->entity,
				$target,
				EntityDamageEvent::CAUSE_ENTITY_ATTACK,
				$this->entity->getProperties()->getAttackDamage()
			);
			$target->attack($ev);
		}
	}

	public function stop() : void{
		$this->entity->setTargetEntity(null);
		$this->navigation->stop();
	}

	public function isInterruptable() : bool{
		return false;
	}
}
