<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Fence;
use pocketmine\item\Item;

/**
 * A custom fence block with correct connection behaviour.
 * Two adjacent custom fences (or vanilla fences) will connect automatically
 * because PM5's Fence class handles horizontal connection state.
 *
 * Config:
 *   type: fence
 */
class CustomFenceBlock extends Fence implements CustomBlockInterface{
	use CustomBlockTrait;

	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		CustomBlockProperties $properties
	){
		parent::__construct($idInfo, $properties->getName(), $typeInfo);
		$this->properties = $properties;
	}

	public function getLightLevel() : int{
		return $this->properties->getLightEmission();
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		if($this->properties->hasDrops()){
			return $this->properties->getDropItems();
		}
		return parent::getDropsForCompatibleTool($item);
	}
}
