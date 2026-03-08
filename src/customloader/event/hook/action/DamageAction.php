<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;

/**
 * Deals a fixed amount of magic damage to the source or target entity.
 *
 * YAML fields:
 *   action: damage
 *   amount: 5.0
 *   target: target           # "self" or "target" (default: "target")
 */
final class DamageAction implements EventAction{

    public function __construct(
        private readonly float  $amount,
        private readonly string $target,
    ){}

    public function getActionType() : string{
        return "damage";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $recipient = $this->resolveTarget($source, $target);
        if($recipient === null || $recipient->isClosed()){
            return;
        }

        $event = new EntityDamageEvent(
            $recipient,
            EntityDamageEvent::CAUSE_MAGIC,
            $this->amount,
        );

        $recipient->attack($event);
    }

    private function resolveTarget(Entity $source, ?Entity $target) : ?Entity{
        if($this->target === "self"){
            return $source;
        }
        return $target;
    }

    public static function fromData(array $data) : static{
        return new static(
            amount: (float)  ($data["amount"] ?? 5.0),
            target: (string) ($data["target"] ?? "target"),
        );
    }
}
