<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Teleports the source player to a relative or absolute position.
 *
 * Config (relative offset from current position):
 *   - action: teleport
 *     x: 0
 *     y: 5
 *     z: 0
 *     relative: true    # default: true (offset from current pos)
 *
 * Config (absolute position in current world):
 *   - action: teleport
 *     x: 100
 *     y: 64
 *     z: 200
 *     relative: false
 */
final class TeleportAction implements EventAction{

	private float $x;
	private float $y;
	private float $z;
	private bool  $relative;

	private function __construct(array $data){
		$this->x        = (float) ($data["x"] ?? 0.0);
		$this->y        = (float) ($data["y"] ?? 0.0);
		$this->z        = (float) ($data["z"] ?? 0.0);
		$this->relative = (bool) ($data["relative"] ?? true);
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "teleport";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		if(!($source instanceof Player)){
			return;
		}

		if($this->relative){
			$pos = $source->getPosition();
			$destination = new Position(
				$pos->x + $this->x,
				$pos->y + $this->y,
				$pos->z + $this->z,
				$pos->getWorld()
			);
		}else{
			$destination = new Position(
				$this->x,
				$this->y,
				$this->z,
				$source->getWorld()
			);
		}

		$source->teleport($destination);
	}
}
