<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use pocketmine\entity\Living;
use pocketmine\player\Player;

/**
 * Makes the entity find and target the nearest attackable entity.
 */
final class NearestAttackableGoal implements Goal{

	private int $cooldown = 0;
	private const SEARCH_INTERVAL = 20; // ticks between searches

	public function __construct(
		private CustomEntity $entity,
		private float $searchDistance = 16.0,
		private string $targetType = "player" // "player" or "living"
	){}

	public function canUse() : bool{
		if(--$this->cooldown > 0){
			return false;
		}
		$this->cooldown = self::SEARCH_INTERVAL;

		$target = $this->findTarget();
		if($target !== null){
			$this->entity->setTargetEntity($target);
			return true;
		}
		return false;
	}

	public function canContinueToUse() : bool{
		$target = $this->entity->getTargetEntity();
		if($target === null || !$target->isAlive() || $target->isClosed()){
			return false;
		}
		return $this->entity->getPosition()->distance($target->getPosition()) <= $this->searchDistance * 2;
	}

	public function start() : void{}

	public function tick() : void{}

	public function stop() : void{
		$this->entity->setTargetEntity(null);
	}

	public function isInterruptable() : bool{
		return false;
	}

	private function findTarget() : ?Living{
		$nearest = null;
		$nearestDist = $this->searchDistance;

		$bb = $this->entity->getBoundingBox()->expandedCopy($this->searchDistance, $this->searchDistance, $this->searchDistance);
		foreach($this->entity->getWorld()->getNearbyEntities($bb, $this->entity) as $candidate){
			if($this->targetType === "player" && !($candidate instanceof Player)){
				continue;
			}
			if(!($candidate instanceof Living) || !$candidate->isAlive()){
				continue;
			}

			$dist = $this->entity->getPosition()->distance($candidate->getPosition());
			if($dist < $nearestDist){
				$nearestDist = $dist;
				$nearest = $candidate;
			}
		}
		return $nearest;
	}
}
