<?php

declare(strict_types=1);

namespace customloader\entity\ai\navigation;

use customloader\entity\CustomEntity;
use pocketmine\math\Vector3;
use function atan2;
use function sqrt;

final class PathNavigation{

	private ?Vector3 $targetPos = null;
	private float $speedModifier = 1.0;
	private bool $done = true;

	public function __construct(private CustomEntity $entity){}

	public function moveTo(Vector3 $target, float $speedModifier = 1.0) : void{
		$this->targetPos = $target;
		$this->speedModifier = $speedModifier;
		$this->done = false;
	}

	public function stop() : void{
		$this->targetPos = null;
		$this->done = true;
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3(0.0, $motion->y, 0.0));
	}

	public function isDone() : bool{
		return $this->done;
	}

	public function tick() : void{
		if($this->targetPos === null){
			return;
		}

		$pos = $this->entity->getPosition();
		$dx = $this->targetPos->x - $pos->x;
		$dz = $this->targetPos->z - $pos->z;
		$dist = sqrt($dx * $dx + $dz * $dz);

		if($dist < 0.5){
			$this->stop();
			return;
		}

		$speed = $this->entity->getProperties()->getMovementSpeed() * $this->speedModifier;
		$motion = $this->entity->getMotion();
		$this->entity->setMotion(new Vector3(
			($dx / $dist) * $speed,
			$motion->y,
			($dz / $dist) * $speed
		));

		// Rotate to face movement direction
		$this->entity->setYaw((float) (-atan2($dx, $dz) / M_PI * 180.0));
	}
}
