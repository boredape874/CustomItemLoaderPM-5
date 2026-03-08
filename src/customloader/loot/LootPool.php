<?php

declare(strict_types=1);

namespace customloader\loot;

use pocketmine\item\Item;
use function array_is_list;
use function count;
use function is_array;
use function mt_rand;

/**
 * A single pool inside a loot table.
 *
 * Config shape:
 *   rolls: {min: 1, max: 3}   OR   rolls: 2   (default: 1)
 *   entries:
 *     - id: "minecraft:diamond"
 *       weight: 10
 *       count: {min: 1, max: 2}
 *       chance: 1.0
 */
final class LootPool{

	private int $rollsMin;
	private int $rollsMax;
	/** @var LootEntry[] */
	private array $entries = [];

	public function __construct(array $data){
		$rolls = $data["rolls"] ?? 1;
		if(is_array($rolls)){
			$this->rollsMin = max(0, (int) ($rolls["min"] ?? 1));
			$this->rollsMax = max($this->rollsMin, (int) ($rolls["max"] ?? $this->rollsMin));
		}else{
			$this->rollsMin = max(0, (int) $rolls);
			$this->rollsMax = $this->rollsMin;
		}

		$rawEntries = $data["entries"] ?? [];
		if(is_array($rawEntries) && array_is_list($rawEntries)){
			foreach($rawEntries as $entryData){
				if(is_array($entryData)){
					$this->entries[] = new LootEntry($entryData);
				}
			}
		}
	}

	/**
	 * Rolls the pool and returns all dropped items.
	 *
	 * Each roll picks one entry by weighted random selection, then calls LootEntry::roll()
	 * which applies that entry's own chance check and count randomisation.
	 *
	 * @return Item[]
	 */
	public function roll() : array{
		if(count($this->entries) === 0){
			return [];
		}

		$rollCount = ($this->rollsMin === $this->rollsMax)
			? $this->rollsMin
			: mt_rand($this->rollsMin, $this->rollsMax);

		$result = [];
		for($i = 0; $i < $rollCount; $i++){
			$entry = $this->pickWeighted();
			if($entry === null){
				continue;
			}
			$item = $entry->roll();
			if($item !== null){
				$result[] = $item;
			}
		}
		return $result;
	}

	/**
	 * Picks a random entry using weighted selection.
	 */
	private function pickWeighted() : ?LootEntry{
		$totalWeight = 0;
		foreach($this->entries as $entry){
			$totalWeight += $entry->getWeight();
		}
		if($totalWeight <= 0){
			return null;
		}

		$rand = mt_rand(1, $totalWeight);
		$cumulative = 0;
		foreach($this->entries as $entry){
			$cumulative += $entry->getWeight();
			if($rand <= $cumulative){
				return $entry;
			}
		}
		// Fallback (should not be reached with valid weights)
		return $this->entries[count($this->entries) - 1];
	}

	/** @return LootEntry[] */
	public function getEntries() : array{
		return $this->entries;
	}

	public function getRollsMin() : int{ return $this->rollsMin; }
	public function getRollsMax() : int{ return $this->rollsMax; }
}
