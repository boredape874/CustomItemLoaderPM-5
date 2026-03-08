<?php

declare(strict_types=1);

namespace customloader\item\properties\component;

use pocketmine\inventory\ArmorInventory;
use pocketmine\nbt\tag\CompoundTag;
use function in_array;

final class ArmorComponent extends Component{

	public const TAG_ARMOR = "minecraft:armor";
	public const TAG_WEARABLE = "minecraft:wearable";

	/** @phpstan-param ArmorInventory::SLOT_* $armorSlot */
	public function __construct(
		private readonly string $armorClass,
		private readonly int $armorSlot,
		private readonly int $defensePoints
	){
		static $acceptedArmorValues = ["gold", "none", "leather", "chain", "iron", "diamond", "elytra", "turtle", "netherite"];
		if(!in_array($armorClass, $acceptedArmorValues, true)){
			throw new \InvalidArgumentException("Invalid armor class $armorClass");
		}
	}

	public function getName() : string{
		return "armor";
	}

	public function buildComponent(CompoundTag $rootNBT) : void{
		$componentNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS);
		$componentNBT->setTag(self::TAG_ARMOR, CompoundTag::create());
		$componentNBT->setTag(self::TAG_WEARABLE, CompoundTag::create());
	}

	public function processComponent(CompoundTag $rootNBT) : void{
		$componentNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS);
		$armorNBT = $componentNBT->getCompoundTag(self::TAG_ARMOR);
		$wearableNBT = $componentNBT->getCompoundTag(self::TAG_WEARABLE);
		$armorNBT->setString("texture_type", $this->armorClass);
		$armorNBT->setInt("protection", $this->defensePoints);

		static $armorInfoIntToStringMap = [
			ArmorInventory::SLOT_HEAD => "slot.armor.head",
			ArmorInventory::SLOT_CHEST => "slot.armor.chest",
			ArmorInventory::SLOT_LEGS => "slot.armor.legs",
			ArmorInventory::SLOT_FEET => "slot.armor.feet",
		];

		$wearableNBT->setString("slot", $armorInfoIntToStringMap[$this->armorSlot]);
		$wearableNBT->setByte("dispensable", 1);
	}
}
