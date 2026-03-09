<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

/**
 * Sets, adds, or removes health from the source or target entity.
 *
 * Config:
 *   - action: set_health
 *     amount: 4.0        # hearts: 2.0 = 1 heart
 *     mode: add          # add | remove | set (default: add)
 *     target: source     # "source" | "target" (default: source)
 */
final class SetHealthAction implements EventAction{

	private float  $amount;
	private string $mode;   // "add" | "remove" | "set"
	private string $who;    // "source" | "target"

	private function __construct(array $data){
		$this->amount = (float) ($data["amount"] ?? 4.0);
		$this->mode   = strtolower((string) ($data["mode"]   ?? "add"));
		$this->who    = strtolower((string) ($data["target"] ?? "source"));
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "set_health";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		$entity = ($this->who === "target") ? ($target ?? $source) : $source;

		match($this->mode){
			"set"    => $entity->setHealth(max(0.0, min($this->amount, $entity->getMaxHealth()))),
			"remove" => $entity->attack(
				new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_CUSTOM, $this->amount)
			),
			default  => $entity->heal(  // "add"
				new EntityRegainHealthEvent($entity, $this->amount, EntityRegainHealthEvent::CAUSE_CUSTOM)
			),
		};
	}
}
