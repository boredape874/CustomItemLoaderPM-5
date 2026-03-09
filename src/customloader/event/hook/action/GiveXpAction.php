<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Grants experience points to the source player.
 *
 * Config:
 *   - action: give_xp
 *     amount: 10         # XP points to add (can be negative to remove)
 */
final class GiveXpAction implements EventAction{

	private int $amount;

	private function __construct(array $data){
		$this->amount = (int) ($data["amount"] ?? 1);
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "give_xp";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		if(!($source instanceof Player)){
			return;
		}
		$xpManager = $source->getXpManager();
		if($this->amount >= 0){
			$xpManager->addXp($this->amount);
		}else{
			// Negative amount: remove XP (clamp at 0)
			$current = $xpManager->getCurrentTotalXp();
			$xpManager->setCurrentTotalXp(max(0, $current + $this->amount));
		}
	}
}
