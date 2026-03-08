<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use customloader\entity\CustomEntity;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use function atan2;
use function lcg_value;
use function sqrt;

/**
 * Makes the entity rotate to face a nearby player (or entity).
 */
final class LookAtEntityGoal implements Goal{

	private ?Entity $target = null;
	private int $lookTime = 0;
	private float $lookDistance;

	public function __construct(
		private CustomEntity $entity,
		float $lookDistance = 8.0
	){
		$this->lookDistance = $lookDistance;
	}

	public function canUse() : bool{
		$nearest = null;
		$nearestDist = $this->lookDistance;

		$bb = $this->entity->getBoundingBox()->expandedCopy($this->lookDistance, $this->lookDistance, $this->lookDistance);
		foreach($this->entity->getWorld()->getNearbyEntities($bb, $this->entity) as $candidate){
			if(!($candidate instanceof Player)){
				continue;
			}
			$dist = $this->entity->getPosition()->distance($candidate->getPosition());
			if($dist < $nearestDist){
				$nearestDist = $dist;
				$nearest = $candidate;
			}
		}

		$this->target = $nearest;
		if($this->target !== null){
			$this->lookTime = 40 + (int) (lcg_value() * 40);
		}
		return $this->target !== null;
	}

	public function canContinueToUse() : bool{
		return $this->target !== null
			&& !$this->target->isClosed()
			&& $this->entity->getPosition()->distance($this->target->getPosition()) <= $this->lookDistance
			&& --$this->lookTime > 0;
	}

	public function start() : void{}

	public function tick() : void{
		if($this->target === null) return;

		$diff = $this->target->getPosition()->subtract($this->entity->getPosition());
		$dist2d = sqrt($diff->x ** 2 + $diff->z ** 2);

		$yaw = (float) (-atan2($diff->x, $diff->z) / M_PI * 180.0);
		$pitch = (float) (-atan2($diff->y, $dist2d) / M_PI * 180.0);

		$this->entity->setYaw($yaw);
		$this->entity->setPitch($pitch);
	}

	public function stop() : void{
		$this->target = null;
	}

	public function isInterruptable() : bool{
		return true;
	}
}
