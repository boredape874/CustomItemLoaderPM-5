<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\item\CustomItemInterface;
use pocketmine\player\Player;

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
 *
 * @see AbstractParticleTask  for tick management and world filtering
 */
final class ArmorWearTask extends AbstractParticleTask{

	protected function getParticleData(Player $player) : array{
		$armorInv = $player->getArmorInventory();
		$result   = [];
		foreach([$armorInv->getHelmet(), $armorInv->getChestplate(), $armorInv->getLeggings(), $armorInv->getBoots()] as $item){
			if(!($item instanceof CustomItemInterface)){
				continue;
			}
			$props    = $item->getProperties();
			$result[] = [$props->getWearParticle(), $props->getWearParticleInterval(), 1.5];
		}
		return $result;
	}
}
