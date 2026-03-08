<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;
use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\item\Item;

class CustomBlock extends Block implements CustomBlockInterface{
	use CustomBlockTrait;

	public function __construct(BlockIdentifier $idInfo, BlockTypeInfo $typeInfo, CustomBlockProperties $properties){
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
