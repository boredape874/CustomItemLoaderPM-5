<?php

declare(strict_types=1);

namespace customloader\item;

use customloader\item\properties\CustomItemProperties;
use pocketmine\item\Armor;
use pocketmine\item\ArmorTypeInfo;
use pocketmine\item\ItemIdentifier;

class CustomArmorItem extends Armor implements CustomItemInterface{
	use CustomItemTrait;

	public function __construct(string $name, CustomItemProperties $properties){
		$this->properties = $properties;
		parent::__construct(
			new ItemIdentifier($this->properties->getId()),
			$this->properties->getName(),
			new ArmorTypeInfo(
				$this->properties->getDefencePoints(),
				$this->properties->getMaxDurability(),
				$this->properties->getArmorSlot()
			)
		);
	}
}
