<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use pocketmine\math\Vector3;

/**
 * Makes the entity swim upward when submerged in water.
 */
final class FloatGoal implements Goal{

	public function __construct(private CustomEntity $entity){}

	public function canUse() : bool{
		return $this->entity->isUnderwater() || $this->entity->isInWater();
	}

	public function canContinueToUse() : bool{
		return $this->canUse();
	}

	public function start() : void{}

	public function tick() : void{
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3($motion->x, 0.08, $motion->z));
	}

	public function stop() : void{}

	public function isInterruptable() : bool{
		return false;
	}
}
