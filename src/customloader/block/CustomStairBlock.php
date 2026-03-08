<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Stair;
use pocketmine\item\Item;

/**
 * A custom stair block that extends PM5's Stair for correct shape/collision/facing
 * behaviour, while carrying CustomBlockProperties for name, drops, and other config-driven
 * data.
 *
 * Registration notes (CustomBlockManager):
 *  - Use GlobalBlockStateHandlers::getRegistrar()->mapSimple() for serialization.
 *  - Stair has 2-bit horizontal facing + 1-bit upsideDown state — 8 permutations total.
 *    mapSimple() handles this automatically as long as the block is registered correctly.
 */
class CustomStairBlock extends Stair implements CustomBlockInterface{
	use CustomBlockTrait;

	/**
	 * @param BlockIdentifier       $idInfo
	 * @param BlockTypeInfo         $typeInfo
	 * @param CustomBlockProperties $properties
	 */
	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		CustomBlockProperties $properties
	){
		// Stair has no custom __construct — delegates directly to Transparent -> Block.
		// Block::__construct() accepts (idInfo, name, typeInfo).
		parent::__construct($idInfo, $properties->getName(), $typeInfo);
		$this->properties = $properties;
	}

	public function getLightLevel() : int{
		return $this->properties->getLightEmission();
	}

	/**
	 * Returns custom drops when the block has explicit drop config; otherwise falls back to
	 * the default Block behaviour (drop 1 of self).
	 */
	public function getDropsForCompatibleTool(Item $item) : array{
		if($this->properties->hasDrops()){
			return $this->properties->getDropItems();
		}
		return parent::getDropsForCompatibleTool($item);
	}
}
