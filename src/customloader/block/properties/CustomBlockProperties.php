<?php

declare(strict_types=1);

namespace customloader\block\properties;

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
