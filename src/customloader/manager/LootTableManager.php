<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\loot\LootTable;
use customloader\CustomLoader;
use pocketmine\item\Item;
use pocketmine\utils\SingletonTrait;
use Throwable;
use function count;
use function is_array;

/**
 * Singleton manager for all registered loot tables.
 *
 * Config shape (top-level "loot_tables" key):
 *   loot_tables:
 *     chest_common:
 *       pools:
 *         - rolls: {min: 1, max: 3}
 *           entries:
 *             - id: "minecraft:diamond"
 *               weight: 10
 *               count: {min: 1, max: 2}
 *               chance: 1.0
 */
final class LootTableManager{
	use SingletonTrait;

	/** @var LootTable[] keyed by loot table name */
	private array $tables = [];

	public function __construct(){}

	/**
	 * Parses and registers all loot tables from the plugin config's "loot_tables" section.
	 *
	 * @param array<string, mixed> $data  The raw "loot_tables" array from config.
	 */
	public function registerDefaultLootTables(array $data) : void{
		foreach($data as $name => $tableData){
			$name = (string) $name;
			if(!is_array($tableData)){
				CustomLoader::getInstance()->getLogger()->warning(
					"Loot table '$name' is not a valid array — skipping."
				);
				continue;
			}
			try{
				$this->tables[$name] = new LootTable($name, $tableData);
			}catch(Throwable $e){
				CustomLoader::getInstance()->getLogger()->warning(
					"Failed to load loot table '$name': " . $e->getMessage()
				);
			}
		}
	}

	/**
	 * Registers a single loot table instance directly.
	 */
	public function register(LootTable $table) : void{
		$this->tables[$table->getName()] = $table;
	}

	public function get(string $name) : ?LootTable{
		return $this->tables[$name] ?? null;
	}

	public function has(string $name) : bool{
		return isset($this->tables[$name]);
	}

	/**
	 * Rolls the named loot table and returns the resulting items.
	 * Returns an empty array if the table does not exist.
	 *
	 * @return Item[]
	 */
	public function roll(string $name) : array{
		$table = $this->tables[$name] ?? null;
		if($table === null){
			return [];
		}
		return $table->roll();
	}

	/** @return LootTable[] */
	public function getAll() : array{
		return $this->tables;
	}

	public function getTableCount() : int{
		return count($this->tables);
	}
}
