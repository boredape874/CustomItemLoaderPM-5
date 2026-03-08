<?php

declare(strict_types=1);

namespace customloader\loot;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use function is_array;
use function lcg_value;
use function mt_rand;

/**
 * A single weighted entry in a loot pool.
 *
 * Config shape:
 *   id:     "minecraft:diamond"
 *   weight: 10           (default: 1)
 *   count:  {min: 1, max: 2}   OR   count: 1   (default: 1)
 *   chance: 1.0          (default: 1.0)
 */
final class LootEntry{

	private string $itemId;
	private int $weight;
	private int $countMin;
	private int $countMax;
	private float $chance;

	public function __construct(array $data){
		$this->itemId  = (string) ($data["id"] ?? "");
		$this->weight  = max(1, (int) ($data["weight"] ?? 1));
		$this->chance  = (float) ($data["chance"] ?? 1.0);

		$count = $data["count"] ?? 1;
		if(is_array($count)){
			$this->countMin = max(1, (int) ($count["min"] ?? 1));
			$this->countMax = max($this->countMin, (int) ($count["max"] ?? $this->countMin));
		}else{
			$this->countMin = max(1, (int) $count);
			$this->countMax = $this->countMin;
		}
	}

	/**
	 * Rolls this entry: returns an Item if the chance check passes, null otherwise.
	 * The item count is randomised between countMin and countMax.
	 */
	public function roll() : ?Item{
		if($this->itemId === ""){
			return null;
		}
		// Chance check (lcg_value() returns [0.0, 1.0))
		if($this->chance < 1.0 && lcg_value() > $this->chance){
			return null;
		}

		$item = StringToItemParser::getInstance()->parse($this->itemId);
		if($item === null){
			return null;
		}

		$count = ($this->countMin === $this->countMax)
			? $this->countMin
			: mt_rand($this->countMin, $this->countMax);

		$item->setCount($count);
		return $item;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getItemId() : string{
		return $this->itemId;
	}
}
