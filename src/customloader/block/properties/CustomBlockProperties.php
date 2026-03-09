<?php

declare(strict_types=1);

namespace customloader\block\properties;

use customloader\event\hook\EventAction;
use customloader\event\hook\EventHookParser;
use InvalidArgumentException;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function is_array;
use function lcg_value;
use function strtolower;

final class CustomBlockProperties{

	private string $namespace;
	private string $texture;
	private ?string $model;
	private float $hardness;
	private float $blastResistance;
	private int $toolType;
	private int $toolTier;
	private int $lightEmission;
	/** @var array<array{item: Item, count: int, chance: float}> */
	private array $drops = [];
	private int $creativeCategory;
	private int $typeId;
	/** Block shape: "cube" | "slab" | "stair" | "fence" | "leaves" */
	private string $blockType = "cube";
	/** Loot table name override — when set, LootTableManager is used for drops */
	private ?string $lootTable = null;
	/** XP drop range when block is broken */
	private int $xpDropMin = 0;
	private int $xpDropMax = 0;
	/** Whether leaves block should not decay (only for type: leaves) */
	private bool $noDecay = false;
	/** @var EventAction[]  Actions fired after the block is broken */
	private array $onBreakHooks = [];
	/** @var EventAction[]  Actions fired after the block is placed */
	private array $onPlaceHooks = [];
	/** @var EventAction[]  Actions fired when a player right-clicks the block */
	private array $onInteractHooks = [];

	public function __construct(private string $name, array $data, ?int $presetTypeId = null){
		$this->parseData($data, $presetTypeId);
	}

	private function parseData(array $data, ?int $presetTypeId = null) : void{
		if(!isset($data["namespace"])){
			throw new InvalidArgumentException("namespace is required");
		}
		if(!isset($data["texture"])){
			throw new InvalidArgumentException("texture is required");
		}

		$this->typeId = $presetTypeId ?? BlockTypeIds::newId();
		$this->namespace = (string) $data["namespace"];
		$this->texture = (string) $data["texture"];
		$this->model = isset($data["model"]) ? (string) $data["model"] : null;
		$this->hardness = isset($data["hardness"]) ? (float) $data["hardness"] : 1.5;
		$this->blastResistance = isset($data["blast_resistance"]) ? (float) $data["blast_resistance"] : $this->hardness;
		$this->lightEmission = isset($data["light_emission"]) ? (int) $data["light_emission"] : 0;
		$this->creativeCategory = isset($data["creative_category"]) ? (int) $data["creative_category"] : 4;

		$toolTypeStr = isset($data["tool_type"]) ? strtolower((string) $data["tool_type"]) : "none";
		$this->toolType = match($toolTypeStr){
			"pickaxe" => BlockToolType::PICKAXE,
			"axe" => BlockToolType::AXE,
			"shovel" => BlockToolType::SHOVEL,
			"hoe" => BlockToolType::HOE,
			"sword" => BlockToolType::SWORD,
			"shears" => BlockToolType::SHEARS,
			default => BlockToolType::NONE,
		};
		$this->toolTier = isset($data["tool_tier"]) ? (int) $data["tool_tier"] : 0;

		// Block shape type: cube (default), slab, stair, fence, leaves
		$blockTypeStr = strtolower((string) ($data["type"] ?? "cube"));
		$this->blockType = match($blockTypeStr){
			"slab", "stair", "fence", "leaves" => $blockTypeStr,
			default => "cube",
		};

		// Leaves-specific option
		if($this->blockType === "leaves"){
			$this->noDecay = (bool) ($data["no_decay"] ?? false);
		}

		// XP drop on block break
		if(isset($data["xp_drop"])){
			$xp = $data["xp_drop"];
			if(is_array($xp)){
				$this->xpDropMin = max(0, (int) ($xp["min"] ?? 0));
				$this->xpDropMax = max($this->xpDropMin, (int) ($xp["max"] ?? $this->xpDropMin));
			}else{
				$this->xpDropMin = $this->xpDropMax = max(0, (int) $xp);
			}
		}

		// Optional loot table reference (overrides drops when set)
		if(isset($data["loot_table"]) && $data["loot_table"] !== ""){
			$this->lootTable = (string) $data["loot_table"];
		}

		if(isset($data["drops"]) && is_array($data["drops"])){
			foreach($data["drops"] as $drop){
				if(!isset($drop["id"])) continue;
				$item = StringToItemParser::getInstance()->parse((string) $drop["id"]);
				if($item === null) continue;
				$this->drops[] = [
					"item" => $item,
					"count" => (int) ($drop["count"] ?? 1),
					"chance" => (float) ($drop["chance"] ?? 1.0),
				];
			}
		}

		// Event hooks
		if(isset($data["on_break"]) && is_array($data["on_break"])){
			$this->onBreakHooks = EventHookParser::parse($data["on_break"]);
		}
		if(isset($data["on_place"]) && is_array($data["on_place"])){
			$this->onPlaceHooks = EventHookParser::parse($data["on_place"]);
		}
		if(isset($data["on_interact"]) && is_array($data["on_interact"])){
			$this->onInteractHooks = EventHookParser::parse($data["on_interact"]);
		}
	}

	public function getName() : string{ return $this->name; }
	public function getNamespace() : string{ return $this->namespace; }
	public function getTexture() : string{ return $this->texture; }
	public function getModel() : ?string{ return $this->model; }
	public function getHardness() : float{ return $this->hardness; }
	public function getBlastResistance() : float{ return $this->blastResistance; }
	public function getToolType() : int{ return $this->toolType; }
	public function getToolTier() : int{ return $this->toolTier; }
	public function getLightEmission() : int{ return $this->lightEmission; }
	public function hasDrops() : bool{ return count($this->drops) > 0; }
	public function getCreativeCategory() : int{ return $this->creativeCategory; }
	public function getTypeId() : int{ return $this->typeId; }
	/** Returns the block shape type: "cube" | "slab" | "stair" | "fence" | "leaves" */
	public function getBlockType() : string{ return $this->blockType; }
	/** Returns the optional loot table name, or null if using inline drops */
	public function getLootTable() : ?string{ return $this->lootTable; }
	/** Returns the minimum XP to drop when broken (0 = no XP drop) */
	public function getXpDropMin() : int{ return $this->xpDropMin; }
	/** Returns the maximum XP to drop when broken */
	public function getXpDropMax() : int{ return $this->xpDropMax; }
	/** Whether this leaves block should never decay */
	public function isNoDecay() : bool{ return $this->noDecay; }
	/** @return EventAction[] */
	public function getOnBreakHooks() : array{ return $this->onBreakHooks; }
	/** @return EventAction[] */
	public function getOnPlaceHooks() : array{ return $this->onPlaceHooks; }
	/** @return EventAction[] */
	public function getOnInteractHooks() : array{ return $this->onInteractHooks; }

	/** @return Item[] */
	public function getDropItems() : array{
		$result = [];
		foreach($this->drops as $drop){
			if(lcg_value() <= $drop["chance"]){
				$item = clone $drop["item"];
				$item->setCount($drop["count"]);
				$result[] = $item;
			}
		}
		return $result;
	}
}
