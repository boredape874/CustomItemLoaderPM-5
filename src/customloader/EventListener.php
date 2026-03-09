<?php

declare(strict_types=1);

namespace customloader;

use customloader\event\hook\EventHookParser;
use customloader\item\CustomItemInterface;
use customloader\manager\CustomBlockManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\inventory\FurnaceBurnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\player\Player;
use function count;
use function hash;
use function mt_rand;
use function strcmp;
use function usort;

final class EventListener implements Listener{

	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$packets = $event->getPackets();
		foreach($packets as $packet){
			if($packet instanceof StartGamePacket){
				$packet->levelSettings->experiments = new Experiments([
					"data_driven_items" => true,
					"data_driven_biomes" => false,
					"upcoming_creator_features" => false,
					"gametest" => false,
					"experimental_molang_features" => false,
				], true);

				// Inject custom block palette entries
				$customEntries = CustomBlockManager::getInstance()->getPaletteEntries();
				if(count($customEntries) > 0){
					foreach($customEntries as $entry){
						$packet->blockPalette[] = $entry;
					}
					// Bedrock 1.20.60+ requires block palette sorted by fnv164 hash
					usort($packet->blockPalette, static fn(BlockPaletteEntry $a, BlockPaletteEntry $b) =>
						strcmp(hash("fnv164", $a->getName()), hash("fnv164", $b->getName()))
					);
				}
			}elseif($packet instanceof ResourcePackStackPacket){
				$packet->experiments = new Experiments([
					"data_driven_items" => true,
					"data_driven_biomes" => false,
					"upcoming_creator_features" => false,
					"gametest" => false,
					"experimental_molang_features" => false,
				], true);
			}
		}
	}

	public function onBlockBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlock();
		$manager = CustomBlockManager::getInstance();

		if($manager->isCustomBlock($block)){
			$event->setDrops([]);
			$customDrops = $manager->getDrops($block);
			$pos = $block->getPosition();
			$world = $pos->getWorld();
			$dropPos = $pos->add(0.5, 0.5, 0.5);
			foreach($customDrops as $drop){
				$world->dropItem($dropPos, $drop);
			}

			$customBlock = $manager->getCustomBlock($block);
			if($customBlock !== null){
				$props = $customBlock->getProperties();

				// XP drop
				$xpMin = $props->getXpDropMin();
				$xpMax = $props->getXpDropMax();
				if($xpMax > 0){
					$event->setXpDropAmount(mt_rand($xpMin, $xpMax));
				}

				// on_break hooks
				$hooks = $props->getOnBreakHooks();
				if(count($hooks) > 0){
					EventHookParser::execute($hooks, $event->getPlayer());
				}
			}
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		$transaction = $event->getTransaction();
		$manager = CustomBlockManager::getInstance();

		foreach($transaction->getBlocks() as [$x, $y, $z, $block]){
			if($manager->isCustomBlock($block)){
				$customBlock = $manager->getCustomBlock($block);
				if($customBlock !== null){
					$hooks = $customBlock->getProperties()->getOnPlaceHooks();
					if(count($hooks) > 0){
						EventHookParser::execute($hooks, $event->getPlayer());
					}
				}
				break; // Only fire once per placement transaction
			}
		}
	}

	/**
	 * Fired when a player right-clicks a block.
	 * Triggers on_interact hooks defined in the block's config.
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			return;
		}
		$block = $event->getBlock();
		$manager = CustomBlockManager::getInstance();
		$customBlock = $manager->getCustomBlock($block);
		if($customBlock === null){
			return;
		}
		$hooks = $customBlock->getProperties()->getOnInteractHooks();
		if(count($hooks) > 0){
			EventHookParser::execute($hooks, $event->getPlayer());
		}
	}

	/**
	 * Fired when a player right-clicks with an item (PlayerItemUseEvent).
	 * Triggers on_use hooks (항상) and on_sneak_use hooks (shift 중일 때).
	 */
	public function onPlayerItemUse(PlayerItemUseEvent $event) : void{
		$item = $event->getItem();
		if(!($item instanceof CustomItemInterface)){
			return;
		}

		$props      = $item->getProperties();
		$player     = $event->getPlayer();
		$hooks      = $props->getOnUseHooks();
		$sneakHooks = $props->getOnSneakUseHooks();

		if(count($hooks) === 0 && count($sneakHooks) === 0){
			return;
		}

		if(count($hooks) > 0){
			EventHookParser::execute($hooks, $player);
		}
		if($player->isSneaking() && count($sneakHooks) > 0){
			EventHookParser::execute($sneakHooks, $player);
		}
	}

	/**
	 * Fired when a player finishes eating/drinking an item.
	 * Triggers on_eat hooks defined in the item's config.
	 */
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) : void{
		$item = $event->getItem();
		if(!($item instanceof CustomItemInterface)){
			return;
		}
		$hooks = $item->getProperties()->getOnEatHooks();
		if(count($hooks) === 0){
			return;
		}
		EventHookParser::execute($hooks, $event->getPlayer());
	}

	/**
	 * Fired when an entity is damaged by another entity.
	 * Triggers on_attack hooks for the attacker's held item (if it is a CustomItem).
	 */
	public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void{
		$attacker = $event->getDamager();
		if(!($attacker instanceof Player)){
			return;
		}

		$item = $attacker->getInventory()->getItemInHand();
		if(!($item instanceof CustomItemInterface)){
			return;
		}

		$props = $item->getProperties();

		$hooks = $props->getOnAttackHooks();
		if(count($hooks) > 0){
			EventHookParser::execute($hooks, $attacker, $event->getEntity());
		}

		// on_sneak_attack: shift 누른 채 공격
		if($attacker->isSneaking()){
			$sneakHooks = $props->getOnSneakAttackHooks();
			if(count($sneakHooks) > 0){
				EventHookParser::execute($sneakHooks, $attacker, $event->getEntity());
			}
		}

		// attack_animate: 서버가 AnimatePacket으로 특수 이펙트 브로드캐스트
		$attackAnimate = $props->getAttackAnimate();
		if($attackAnimate !== ""){
			$action = match($attackAnimate){
				"crit"       => AnimatePacket::ACTION_CRITICAL_HIT,
				"magic_crit" => AnimatePacket::ACTION_MAGIC_CRITICAL_HIT,
				default      => null,
			};
			if($action !== null){
				$pk = AnimatePacket::create($attacker->getId(), $action);
				foreach($attacker->getViewers() as $viewer){
					$viewer->getNetworkSession()->sendDataPacket($pk);
				}
				$attacker->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}

	/**
	 * Fired when a furnace begins burning a new fuel item.
	 * Sets the burn duration for custom fuel items.
	 */
	public function onFurnaceBurn(FurnaceBurnEvent $event) : void{
		$fuel = $event->getFuel();
		if(!($fuel instanceof CustomItemInterface)){
			return;
		}
		$burnTime = $fuel->getProperties()->getFuelBurnTime();
		if($burnTime <= 0){
			return;
		}
		$event->setBurnTime($burnTime);
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		// reserved for future use
	}
}
