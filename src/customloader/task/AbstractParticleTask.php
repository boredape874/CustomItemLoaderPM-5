<?php

declare(strict_types=1);

namespace customloader\task;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use function count;
use function in_array;

/**
 * Base class for tasks that periodically spawn particles relative to players.
 *
 * Subclasses implement getParticleData() to return which particles to emit
 * for a given player. The base class handles tick counting, world filtering,
 * and packet construction.
 */
abstract class AbstractParticleTask extends Task{

	private int $tick = 0;
	private bool $hasDisabledWorlds;

	/** @param string[] $disabledWorlds World folder names where particles are suppressed */
	public function __construct(private array $disabledWorlds = []){
		$this->hasDisabledWorlds = count($disabledWorlds) > 0;
	}

	final public function onRun() : void{
		++$this->tick;

		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			if($this->hasDisabledWorlds
				&& in_array($player->getWorld()->getFolderName(), $this->disabledWorlds, true)){
				continue;
			}

			foreach($this->getParticleData($player) as [$particle, $interval, $yOffset]){
				if($particle === "" || $this->tick % $interval !== 0){
					continue;
				}

				$pos    = $player->getPosition();
				$packet = SpawnParticleEffectPacket::create(
					0,   // dimension (0 = overworld)
					-1,  // entityUniqueId (-1 = absolute position)
					new Vector3($pos->getX(), $pos->getY() + $yOffset, $pos->getZ()),
					$particle,
					null // molangVariablesJson
				);
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}

	/**
	 * Returns particle emit data for the given player.
	 *
	 * Each entry must be a tuple of:
	 *   [0] string  $particle  — Bedrock particle namespace ("" to skip)
	 *   [1] int     $interval  — Emit every N ticks (>= 1)
	 *   [2] float   $yOffset   — Y offset above player feet
	 *
	 * @return array{0: string, 1: int, 2: float}[]
	 */
	abstract protected function getParticleData(Player $player) : array;
}
