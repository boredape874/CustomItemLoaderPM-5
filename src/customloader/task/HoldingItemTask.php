<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\item\CustomItemInterface;
use pocketmine\player\Player;

/**
 * 커스텀 아이템의 hold_particle 기능:
 * 플레이어가 해당 아이템을 메인핸드에 들고 있을 때 지정된 파티클을 주기적으로 스폰.
 *
 * Config 예:
 *   hold_particle: "mypack:fire_aura"
 *   hold_particle_interval: 10   # 틱 (기본 20 = 1초)
 *
 * @see AbstractParticleTask  for tick management and world filtering
 */
final class HoldingItemTask extends AbstractParticleTask{

	protected function getParticleData(Player $player) : array{
		$item = $player->getInventory()->getItemInHand();
		if(!($item instanceof CustomItemInterface)){
			return [];
		}
		$props = $item->getProperties();
		return [[$props->getHoldParticle(), $props->getHoldParticleInterval(), 1.0]];
	}
}
