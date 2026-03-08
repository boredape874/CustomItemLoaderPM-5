<?php

declare(strict_types=1);

namespace customloader\loot;

use pocketmine\item\Item;
use function array_is_list;
use function array_merge;
use function is_array;

/**
 * A named loot table consisting of one or more loot pools.
 *
 * Config shape:
 *   pools:
 *     - rolls: {min: 1, max: 3}
 *       entries:
 *         - id: "minecraft:diamond"
 *           weight: 10
 *           count: {min: 1, max: 2}
 *           chance: 1.0
 *         - id: "minecraft:iron_ingot"
 *           weight: 20
 *           count: 1
 */
final class LootTable{

	/** @var LootPool[] */
	private array $pools = [];

	public function __construct(
		private readonly string $name,
		array $data
	){
		$rawPools = $data["pools"] ?? [];
		if(is_array($rawPools) && array_is_list($rawPools)){
			foreach($rawPools as $poolData){
				if(is_array($poolData)){
					$this->pools[] = new LootPool($poolData);
				}
			}
		}
	}

	/**
	 * Rolls every pool in the table and returns all resulting items.
	 *
	 * @return Item[]
	 */
	public function roll() : array{
		$result = [];
		foreach($this->pools as $pool){
			$result = array_merge($result, $pool->roll());
		}
		return $result;
	}

	public function getName() : string{
		return $this->name;
	}

	/** @return LootPool[] */
	public function getPools() : array{
		return $this->pools;
	}
}
