<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Leaves;
use pocketmine\item\Item;

/**
 * A custom leaves block with optional no-decay behaviour.
 *
 * Config:
 *   type: leaves
 *   no_decay: true   # optional — prevents the leaves from decaying (default: false)
 *
 * When no_decay is true the block is immediately marked as persistent on placement,
 * matching vanilla behaviour for player-placed leaves.
 */
class CustomLeavesBlock extends Leaves implements CustomBlockInterface{
	use CustomBlockTrait;

	private bool $noDecay;

	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		CustomBlockProperties $properties
	){
		parent::__construct($idInfo, $properties->getName(), $typeInfo);
		$this->properties = $properties;
		$this->noDecay    = $properties->isNoDecay();

		if($this->noDecay){
			// Persistent leaves never decay regardless of log distance
			$this->persistent = true;
		}
	}

	public function getLightLevel() : int{
		return $this->properties->getLightEmission();
	}

	public function onNearbyBlockChange() : void{
		if($this->noDecay){
			// Skip vanilla decay logic — always persistent
			return;
		}
		parent::onNearbyBlockChange();
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		if($this->properties->hasDrops()){
			return $this->properties->getDropItems();
		}
		return parent::getDropsForCompatibleTool($item);
	}
}
