<?php

declare(strict_types=1);

namespace customloader\entity\spawn;

use pocketmine\data\bedrock\BiomeIds;

/**
 * Parses and stores the spawn conditions for a single custom entity type.
 *
 * Config format (under entities.<name>.spawn):
 * ```yaml
 * spawn:
 *   enabled: true
 *   biomes: [plains, forest]   # empty / omitted = any biome
 *   time: night                # day | night | any
 *   light_max: 7
 *   light_min: 0
 *   min_group: 1
 *   max_group: 3
 *   weight: 10
 *   min_y: 0
 *   max_y: 128
 *   max_spawned: 10
 * ```
 */
final class SpawnRule{

	private const TIME_ANY   = "any";
	private const TIME_DAY   = "day";
	private const TIME_NIGHT = "night";

	// World time ticks:  0 = dawn, 6000 = noon, 12000 = dusk, 18000 = midnight
	// "Day"   = ticks  1000 – 11999  (sun fully up)
	// "Night" = ticks 13000 – 23000  (moon fully up)
	private const DAY_START   = 1000;
	private const DAY_END     = 11999;
	private const NIGHT_START = 13000;
	private const NIGHT_END   = 23000;

	/** @var array<string, int> human-readable biome name → BiomeIds constant */
	private static array $BIOME_MAP = [
		"ocean"         => BiomeIds::OCEAN,
		"plains"        => BiomeIds::PLAINS,
		"desert"        => BiomeIds::DESERT,
		"mountains"     => BiomeIds::EXTREME_HILLS,
		"forest"        => BiomeIds::FOREST,
		"taiga"         => BiomeIds::TAIGA,
		"swamp"         => BiomeIds::SWAMPLAND,
		"river"         => BiomeIds::RIVER,
		"nether"        => BiomeIds::HELL,
		"end"           => BiomeIds::THE_END,
		"snowy_tundra"  => BiomeIds::ICE_PLAINS,
		"mushroom"      => BiomeIds::MUSHROOM_ISLAND,
		"beach"         => BiomeIds::BEACH,
		"jungle"        => BiomeIds::JUNGLE,
		"savanna"       => BiomeIds::SAVANNA,
		"mesa"          => BiomeIds::MESA,
		"birch_forest"  => BiomeIds::BIRCH_FOREST,
		"roofed_forest" => BiomeIds::ROOFED_FOREST,
		// Additional common names
		"cold_taiga"    => BiomeIds::COLD_TAIGA,
		"mega_taiga"    => BiomeIds::MEGA_TAIGA,
		"deep_ocean"    => BiomeIds::DEEP_OCEAN,
		"stone_beach"   => BiomeIds::STONE_BEACH,
		"cold_beach"    => BiomeIds::COLD_BEACH,
		"frozen_river"  => BiomeIds::FROZEN_RIVER,
		"ice_mountains" => BiomeIds::ICE_MOUNTAINS,
		"flower_forest" => BiomeIds::FLOWER_FOREST,
		"mangrove_swamp"=> BiomeIds::MANGROVE_SWAMP,
		"cherry_grove"  => BiomeIds::CHERRY_GROVE,
		"lush_caves"    => BiomeIds::LUSH_CAVES,
		"deep_dark"     => BiomeIds::DEEP_DARK,
		"soulsand_valley"  => BiomeIds::SOULSAND_VALLEY,
		"crimson_forest"   => BiomeIds::CRIMSON_FOREST,
		"warped_forest"    => BiomeIds::WARPED_FOREST,
		"basalt_deltas"    => BiomeIds::BASALT_DELTAS,
		"meadow"           => BiomeIds::MEADOW,
		"grove"            => BiomeIds::GROVE,
		"frozen_peaks"     => BiomeIds::FROZEN_PEAKS,
		"jagged_peaks"     => BiomeIds::JAGGED_PEAKS,
		"snowy_slopes"     => BiomeIds::SNOWY_SLOPES,
		"stony_peaks"      => BiomeIds::STONY_PEAKS,
	];

	private bool   $enabled;
	private string $time;
	private int    $lightMin;
	private int    $lightMax;
	private int    $minGroup;
	private int    $maxGroup;
	private int    $weight;
	private int    $minY;
	private int    $maxY;
	private int    $maxSpawned;

	/** @var int[] resolved biome IDs; empty = any biome is allowed */
	private array $biomeIds = [];

	public function __construct(
		private string $entityNamespace,
		array $spawnData
	){
		$this->enabled    = (bool)   ($spawnData["enabled"]     ?? true);
		$this->time       = (string) ($spawnData["time"]        ?? self::TIME_ANY);
		$this->lightMin   = (int)    ($spawnData["light_min"]   ?? 0);
		$this->lightMax   = (int)    ($spawnData["light_max"]   ?? 15);
		$this->minGroup   = max(1, (int) ($spawnData["min_group"] ?? 1));
		$this->maxGroup   = max($this->minGroup, (int) ($spawnData["max_group"] ?? 3));
		$this->weight     = max(1, (int) ($spawnData["weight"]     ?? 10));
		$this->minY       = (int)    ($spawnData["min_y"]       ?? 0);
		$this->maxY       = (int)    ($spawnData["max_y"]       ?? 255);
		$this->maxSpawned = (int)    ($spawnData["max_spawned"] ?? 10);

		// Clamp light values to valid range 0-15
		$this->lightMin = max(0, min(15, $this->lightMin));
		$this->lightMax = max(0, min(15, $this->lightMax));

		// Resolve biome names to IDs
		if(isset($spawnData["biomes"]) && is_array($spawnData["biomes"])){
			foreach($spawnData["biomes"] as $biomeName){
				$key = strtolower((string) $biomeName);
				if(isset(self::$BIOME_MAP[$key])){
					$this->biomeIds[] = self::$BIOME_MAP[$key];
				}
				// Unknown biome names are silently ignored
			}
		}
	}

	public function getEntityNamespace() : string{
		return $this->entityNamespace;
	}

	public function getWeight() : int{
		return $this->weight;
	}

	public function getMinGroup() : int{
		return $this->minGroup;
	}

	public function getMaxGroup() : int{
		return $this->maxGroup;
	}

	public function getMinY() : int{
		return $this->minY;
	}

	public function getMaxY() : int{
		return $this->maxY;
	}

	public function getMaxSpawned() : int{
		return $this->maxSpawned;
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	/**
	 * Returns true when the given world time satisfies this rule's time condition.
	 *
	 * @param int $worldTime 0-23999
	 */
	public function canSpawnAtTime(int $worldTime) : bool{
		return match($this->time){
			self::TIME_DAY   => $worldTime >= self::DAY_START   && $worldTime <= self::DAY_END,
			self::TIME_NIGHT => $worldTime >= self::NIGHT_START || $worldTime <= 1000,
			default          => true, // TIME_ANY
		};
	}

	/**
	 * Returns true when the given light level satisfies this rule's light range.
	 *
	 * @param int $lightLevel 0-15
	 */
	public function canSpawnAtLight(int $lightLevel) : bool{
		return $lightLevel >= $this->lightMin && $lightLevel <= $this->lightMax;
	}

	/**
	 * Returns true when the given biome ID satisfies this rule.
	 * An empty biome list means any biome is accepted.
	 *
	 * @param int $biomeId from World::getBiomeId()
	 */
	public function canSpawnInBiome(int $biomeId) : bool{
		if(count($this->biomeIds) === 0){
			return true;
		}
		return in_array($biomeId, $this->biomeIds, true);
	}

	/**
	 * Returns the resolved biome ID list (empty = any).
	 *
	 * @return int[]
	 */
	public function getBiomeIds() : array{
		return $this->biomeIds;
	}
}
