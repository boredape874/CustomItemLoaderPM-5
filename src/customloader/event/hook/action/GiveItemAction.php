<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;

/**
 * Gives an item to the source player or a target player.
 *
 * YAML fields:
 *   action: give_item
 *   id:     "minecraft:diamond"
 *   count:  1                  # default: 1
 *   target: self               # "self" (default) or "target" — recipient must be a Player
 */
final class GiveItemAction implements EventAction{

    public function __construct(
        private readonly string $itemId,
        private readonly int    $count,
        private readonly string $target,
    ){}

    public function getActionType() : string{
        return "give_item";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $recipient = $this->resolveTarget($source, $target);
        if(!($recipient instanceof Player)){
            // Items can only be placed in a Player's inventory.
            return;
        }

        $item = StringToItemParser::getInstance()->parse($this->itemId);
        if($item === null){
            return;
        }

        $item->setCount(max(1, $this->count));
        $recipient->getInventory()->addItem($item);
    }

    private function resolveTarget(Entity $source, ?Entity $target) : ?Entity{
        if($this->target === "target"){
            return $target;
        }
        return $source;
    }

    public static function fromData(array $data) : static{
        return new static(
            itemId: (string) ($data["id"]     ?? "minecraft:air"),
            count:  (int)    ($data["count"]  ?? 1),
            target: (string) ($data["target"] ?? "self"),
        );
    }
}
