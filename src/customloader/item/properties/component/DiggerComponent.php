<?php

declare(strict_types=1);

namespace customloader\item\properties\component;

use customloader\util\InvalidNBTStateException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use function implode;
use function in_array;

final class DiggerComponent extends Component{

	public const ACCEPTED_BLOCK_TAGS = [
		"wood", "pumpkin", "plant", "stone", "metal",
		"diamond_pick_diggable", "gold_pick_diggable", "iron_pick_diggable",
		"stone_pick_diggable", "wood_pick_diggable", "dirt", "sand", "gravel",
		"snow", "rail", "water", "mob_spawner", "lush_plants_replaceable",
		"azalea_log_replaceable", "not_feature_replaceable", "text_sign",
		"minecraft:crop", "fertilize_area"
	];

	public const TAG_DIGGER = "minecraft:digger";
	public const TAG_USE_EFFICIENCY = "use_efficiency";
	public const TAG_DESTROY_SPEEDS = "destroy_speeds";

	public function __construct(
		private readonly int $speed,
		private readonly array $blockTags = []
	){}

	public function getName() : string{
		return "digger";
	}

	public function buildComponent(CompoundTag $rootNBT) : void{
		$componentNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS);
		if($componentNBT === null){
			throw new InvalidNBTStateException("Component tree is not built");
		}
		$componentNBT->setTag(self::TAG_DIGGER, CompoundTag::create()
			->setByte(self::TAG_USE_EFFICIENCY, 1)
			->setTag(self::TAG_DESTROY_SPEEDS, new ListTag([]))
		);
	}

	public function processComponent(CompoundTag $rootNBT) : void{
		$diggerNBT = $rootNBT->getCompoundTag(self::TAG_COMPONENTS)?->getCompoundTag(self::TAG_DIGGER);
		if($diggerNBT === null){
			throw new InvalidNBTStateException("Component tree is not built");
		}
		$destroySpeeds = $diggerNBT->getListTag(self::TAG_DESTROY_SPEEDS);
		if($destroySpeeds === null){
			throw new InvalidNBTStateException("Component tree is not built");
		}
		foreach($this->blockTags as $tag){
			if(!in_array($tag, self::ACCEPTED_BLOCK_TAGS, true)){
				throw new \InvalidArgumentException("Invalid block tag $tag");
			}
		}
		$destroySpeeds->push(
			CompoundTag::create()
				->setTag("block", CompoundTag::create()
					->setString("tags", "q.any_tag('" . implode("', '", $this->blockTags) . "')")
				)
				->setInt("speed", $this->speed)
		);
	}
}
