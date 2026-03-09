<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;

/**
 * Sets an entity on fire for the given duration.
 *
 * Config:
 *   - action: set_on_fire
 *     seconds: 5          # duration in seconds
 *     target: target      # "source" | "target" (default: target)
 */
final class SetOnFireAction implements EventAction{

	private int    $seconds;
	private string $who; // "source" | "target"

	private function __construct(array $data){
		$this->seconds = max(1, (int) ($data["seconds"] ?? 5));
		$this->who     = strtolower((string) ($data["target"] ?? "target"));
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "set_on_fire";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		$entity = ($this->who === "source") ? $source : ($target ?? $source);
		$entity->setOnFire($this->seconds);
	}
}
