<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\entity\CustomEntity;
use customloader\entity\spawn\SpawnRule;
use customloader\manager\CustomEntityManager;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\Location;
use pocketmine\world\World;
use function count;
use function mt_rand;

/**
 * Periodically attempts to spawn custom entities in loaded worlds.
 *
 * Schedule this task with a period of 400 ticks (20 seconds) in onEnable():
 * ```php
 * $this->getScheduler()->scheduleRepeatingTask(
 *     new EntitySpawnTask(CustomEntityManager::getInstance()->getSpawnRules()),
 *     400
 * );
 * ```
 *
 * Spawn logic per tick:
 *  - Skip worlds with no online players.
 *  - For each player, attempt up to 3 random positions in a horizontal ring
 *    24-128 blocks away (Y constrained by SpawnRule::minY/maxY).
 *  - Filter eligible rules by time, light level, biome, and max-spawned cap.
 *  - Weighted-random pick among eligible rules.
 *  - Spawn mt_rand(minGroup, maxGroup) entities at the chosen position.
 */
final class EntitySpawnTask extends Task{

	/** Minimum horizontal distance from player to spawn (blocks) */
	private const MIN_DISTANCE = 24;
	/** Maximum horizontal distance from player to spawn (blocks) */
	private const MAX_DISTANCE = 128;
	/** Number of random positions to attempt per player per run */
	private const ATTEMPTS_PER_PLAYER = 3;

	/**
	 * @param SpawnRule[] $spawnRules All registered spawn rules.
	 */
	public function __construct(private array $spawnRules){}

	public function onRun() : void{
		if(count($this->spawnRules) === 0){
			return;
		}

		foreach(Server::getInstance()->getWorldManager()->getWorlds() as $world){
			$players = $world->getPlayers();
			if(count($players) === 0){
				continue;
			}

			$worldTime = $world->getTime() % 24000;

			// Count already-spawned custom entities per namespace in this world
			/** @var array<string, int> $spawnedCount */
			$spawnedCount = $this->countCustomEntities($world);

			foreach($players as $player){
				$this->attemptSpawnsForPlayer($player, $world, $worldTime, $spawnedCount);
			}
		}
	}

	/**
	 * @return array<string, int> namespace => count of living CustomEntities in the world
	 */
	private function countCustomEntities(World $world) : array{
		$counts = [];
		foreach($world->getEntities() as $entity){
			if(!($entity instanceof CustomEntity)){
				continue;
			}
			$props = $entity->getProperties();
			if($props === null){
				continue;
			}
			$ns = $props->getNamespace();
			$counts[$ns] = ($counts[$ns] ?? 0) + 1;
		}
		return $counts;
	}

	/**
	 * Try ATTEMPTS_PER_PLAYER random positions near the given player.
	 *
	 * @param array<string, int> $spawnedCount Reference — updated after each successful spawn.
	 */
	private function attemptSpawnsForPlayer(
		Player $player,
		World $world,
		int $worldTime,
		array &$spawnedCount
	) : void{
		$playerPos = $player->getPosition();
		$px = (int) $playerPos->getX();
		$pz = (int) $playerPos->getZ();

		for($attempt = 0; $attempt < self::ATTEMPTS_PER_PLAYER; $attempt++){
			// Random angle and distance in horizontal ring [MIN_DISTANCE, MAX_DISTANCE]
			$angle    = mt_rand(0, 359);
			$distance = mt_rand(self::MIN_DISTANCE, self::MAX_DISTANCE);

			$spawnX = $px + (int) ($distance * cos(deg2rad($angle)));
			$spawnZ = $pz + (int) ($distance * sin(deg2rad($angle)));

			// Query world conditions at the candidate column
			$biomeId    = $world->getBiomeId($spawnX, $spawnZ);
			$lightLevel = $world->getFullLightAt($spawnX, (int) $playerPos->getY(), $spawnZ);

			// Filter rules that pass all conditions
			$eligible = $this->filterEligibleRules(
				$worldTime,
				$lightLevel,
				$biomeId,
				$spawnedCount
			);

			if(count($eligible) === 0){
				continue;
			}

			$rule = $this->weightedRandom($eligible);
			if($rule === null){
				continue;
			}

			// Pick a Y level inside the rule's allowed Y range
			$minY  = max($rule->getMinY(), $world->getMinY());
			$maxY  = min($rule->getMaxY(), $world->getMaxBuildHeight() - 1);
			if($minY > $maxY){
				continue;
			}
			$spawnY = mt_rand($minY, $maxY);

			// Spawn the group
			$count = mt_rand($rule->getMinGroup(), $rule->getMaxGroup());
			$this->spawnGroup($rule, $world, $spawnX, $spawnY, $spawnZ, $count);

			// Update the in-memory counter so subsequent attempts in this tick
			// respect the max_spawned cap
			$ns = $rule->getEntityNamespace();
			$spawnedCount[$ns] = ($spawnedCount[$ns] ?? 0) + $count;
		}
	}

	/**
	 * Filter rules that are enabled and satisfy time/light/biome/max_spawned conditions.
	 *
	 * @param SpawnRule[]        $rules        (uses $this->spawnRules when omitted)
	 * @param array<string, int> $spawnedCount
	 * @return SpawnRule[]
	 */
	private function filterEligibleRules(
		int $worldTime,
		int $lightLevel,
		int $biomeId,
		array $spawnedCount
	) : array{
		$eligible = [];
		foreach($this->spawnRules as $rule){
			if(!$rule->isEnabled()){
				continue;
			}
			if(!$rule->canSpawnAtTime($worldTime)){
				continue;
			}
			if(!$rule->canSpawnAtLight($lightLevel)){
				continue;
			}
			if(!$rule->canSpawnInBiome($biomeId)){
				continue;
			}
			$ns = $rule->getEntityNamespace();
			if(($spawnedCount[$ns] ?? 0) >= $rule->getMaxSpawned()){
				continue;
			}
			$eligible[] = $rule;
		}
		return $eligible;
	}

	/**
	 * Weighted random selection: picks a rule proportional to its weight.
	 *
	 * @param SpawnRule[] $rules Non-empty list.
	 */
	private function weightedRandom(array $rules) : ?SpawnRule{
		$totalWeight = 0;
		foreach($rules as $rule){
			$totalWeight += $rule->getWeight();
		}
		if($totalWeight <= 0){
			return null;
		}

		$roll = mt_rand(1, $totalWeight);
		$accumulated = 0;
		foreach($rules as $rule){
			$accumulated += $rule->getWeight();
			if($roll <= $accumulated){
				return $rule;
			}
		}
		// Fallback (should not be reached)
		return $rules[array_key_last($rules)];
	}

	/**
	 * Spawns $count entities of the given rule at the given block column.
	 * Entities are placed within a small random scatter (±1 block XZ) to
	 * avoid stacking them all on the exact same block.
	 */
	private function spawnGroup(
		SpawnRule $rule,
		World $world,
		int $centerX,
		int $centerY,
		int $centerZ,
		int $count
	) : void{
		$manager = CustomEntityManager::getInstance();
		for($i = 0; $i < $count; $i++){
			$x = (float) ($centerX + mt_rand(-1, 1));
			$y = (float) $centerY;
			$z = (float) ($centerZ + mt_rand(-1, 1));

			$location = new Location($x, $y, $z, $world, (float) mt_rand(0, 359), 0.0);
			$manager->spawnEntity($location, $rule->getEntityNamespace());
		}
	}
}
