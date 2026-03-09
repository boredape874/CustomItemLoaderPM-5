<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\item\CustomItemInterface;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\scheduler\Task;
use pocketmine\Server;

/**
 * 커스텀 방어구의 wear_particle 기능:
 * 플레이어가 해당 방어구를 착용 중일 때 지정된 파티클을 주기적으로 스폰.
 *
 * Config 예:
 *   items:
 *     fire_chestplate:
 *       armor: true
 *       armor_slot: chest
 *       wear_particle: "mypack:fire_aura"
 *       wear_particle_interval: 15
 */
final class ArmorWearTask extends Task{

	private int $tick = 0;

	public function onRun() : void{
		++$this->tick;

		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			$armorInv = $player->getArmorInventory();
			$slots    = [
				$armorInv->getHelmet(),
				$armorInv->getChestplate(),
				$armorInv->getLeggings(),
				$armorInv->getBoots(),
			];

			foreach($slots as $item){
				if(!($item instanceof CustomItemInterface)){
					continue;
				}

				$props    = $item->getProperties();
				$particle = $props->getWearParticle();
				if($particle === ""){
					continue;
				}

				$interval = $props->getWearParticleInterval();
				if($this->tick % $interval !== 0){
					continue;
				}

				$pos    = $player->getPosition();
				$packet = SpawnParticleEffectPacket::create(
					0,   // dimension (0 = overworld)
					-1,  // entityUniqueId (-1 = absolute position)
					new Vector3($pos->getX(), $pos->getY() + 1.5, $pos->getZ()),
					$particle,
					null
				);
				$player->getNetworkSession()->sendDataPacket($packet);
			}
		}
	}
}
