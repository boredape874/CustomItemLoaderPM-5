<?php

declare(strict_types=1);

namespace customloader\item\properties\component;

use customloader\util\InvalidNBTStateException;
use pocketmine\nbt\tag\CompoundTag;

final class DurableComponent extends Component{

	public const TAG_DURABILITY = "minecraft:durability";

	public function __construct(private readonly int $maxDurability){}

	public function getName() : string{
		return "durable";
	}

	public function buildComponent(CompoundTag $rootNBT) : void{
		$componentNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS);
		if($componentNBT === null){
			throw new InvalidNBTStateException("Component tree is not built");
		}
		$componentNBT->setTag(self::TAG_DURABILITY, CompoundTag::create());
	}

	public function processComponent(CompoundTag $rootNBT) : void{
		$durableNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS)?->getCompoundTag(self::TAG_DURABILITY);
		if($durableNBT === null){
			throw new InvalidNBTStateException("Component tree is not built");
		}
		$durableNBT->setTag("damage_chance", CompoundTag::create()
			->setInt("min", 100)
			->setInt("max", 100)
		);
		$durableNBT->setInt("max_durability", $this->maxDurability);
	}
}
