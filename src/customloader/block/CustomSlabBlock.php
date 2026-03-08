<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Slab;
use pocketmine\block\utils\SlabType;
use pocketmine\item\Item;

/**
 * A custom slab block that extends PM5's Slab for correct shape/collision behaviour,
 * while carrying CustomBlockProperties for name, drops, and other config-driven data.
 *
 * Registration notes (CustomBlockManager):
 *  - Use GlobalBlockStateHandlers::getRegistrar()->mapSimple() for serialization.
 *  - The block name passed to parent::__construct() already gets " Slab" appended by
 *    Slab::__construct(), so CustomBlockProperties::getName() should NOT include "Slab".
 */
class CustomSlabBlock extends Slab implements CustomBlockInterface{
	use CustomBlockTrait;

	/**
	 * @param BlockIdentifier      $idInfo
	 * @param BlockTypeInfo        $typeInfo
	 * @param CustomBlockProperties $properties
	 */
	public function __construct(
		BlockIdentifier $idInfo,
		BlockTypeInfo $typeInfo,
		CustomBlockProperties $properties
	){
		// Slab::__construct() appends " Slab" to the name internally.
		parent::__construct($idInfo, $properties->getName(), $typeInfo);
		$this->properties = $properties;
	}

	public function getLightLevel() : int{
		return $this->properties->getLightEmission();
	}

	/**
	 * Returns custom drops when the block has explicit drop config; otherwise falls back to
	 * the vanilla Slab behaviour (drop 1 or 2 of self depending on slab type).
	 */
	public function getDropsForCompatibleTool(Item $item) : array{
		if($this->properties->hasDrops()){
			// For a double slab, return double the configured drops to stay consistent
			// with vanilla Slab behaviour of dropping 2 items.
			if($this->slabType === SlabType::DOUBLE){
				$single = $this->properties->getDropItems();
				return array_merge($single, $this->properties->getDropItems());
			}
			return $this->properties->getDropItems();
		}
		return parent::getDropsForCompatibleTool($item);
	}
}
