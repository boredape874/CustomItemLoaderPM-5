<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\item\CustomItemInterface;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use function count;
use function in_array;

/**
 * 커스텀 아이템의 hold_particle 기능:
 * 플레이어가 해당 아이템을 메인핸드에 들고 있을 때 지정된 파티클을 주기적으로 스폰.
 *
 * Config 예:
 *   hold_particle: "mypack:fire_aura"
 *   hold_particle_interval: 10   # 틱 (기본 20 = 1초)
 *
 * @param string[] $disabledWorlds 파티클을 비활성화할 월드 폴더명 목록
 */
final class HoldingItemTask extends Task{

	private int $tick = 0;

	/** @param string[] $disabledWorlds */
	public function __construct(private array $disabledWorlds = []){}

	public function onRun() : void{
		++$this->tick;

		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			if(count($this->disabledWorlds) > 0
				&& in_array($player->getWorld()->getFolderName(), $this->disabledWorlds, true)){
				continue;
			}
			$item = $player->getInventory()->getItemInHand();
			if(!($item instanceof CustomItemInterface)){
				continue;
			}

			$props    = $item->getProperties();
			$particle = $props->getHoldParticle();
			if($particle === ""){
				continue;
			}

			$interval = $props->getHoldParticleInterval();
			if($this->tick % $interval !== 0){
				continue;
			}

			$pos    = $player->getPosition();
			$packet = SpawnParticleEffectPacket::create(
				0,   // dimension (0 = overworld)
				-1,  // entityUniqueId (-1 = absolute position)
				new Vector3($pos->getX(), $pos->getY() + 1.0, $pos->getZ()),
				$particle,
				null // molangVariablesJson
			);
			$player->getNetworkSession()->sendDataPacket($packet);
		}
	}
}
