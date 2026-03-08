<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use customloader\entity\ai\navigation\PathNavigation;
use pocketmine\math\Vector3;
use function lcg_value;

/**
 * Makes the entity wander randomly around its current position.
 */
final class RandomStrollGoal implements Goal{

	private const INTERVAL = 120; // ticks between stroll attempts

	private int $cooldown = 0;
	private float $speedModifier;

	public function __construct(
		private CustomEntity $entity,
		private PathNavigation $navigation,
		float $speedModifier = 1.0
	){
		$this->speedModifier = $speedModifier;
	}

	public function canUse() : bool{
		if(--$this->cooldown > 0){
			return false;
		}
		$this->cooldown = self::INTERVAL;
		return true;
	}

	public function canContinueToUse() : bool{
		return !$this->navigation->isDone();
	}

	public function start() : void{
		$pos = $this->entity->getPosition();
		$target = new Vector3(
			$pos->x + (lcg_value() - 0.5) * 20,
			$pos->y,
			$pos->z + (lcg_value() - 0.5) * 20
		);
		$this->navigation->moveTo($target, $this->speedModifier);
	}

	public function tick() : void{
		// Navigation handles movement each tick
	}

	public function stop() : void{
		$this->navigation->stop();
	}

	public function isInterruptable() : bool{
		return true;
	}
}
