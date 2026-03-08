<?php

declare(strict_types=1);

namespace customloader;

use customloader\event\hook\EventHookParser;
use customloader\item\CustomItem;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomItemManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\player\Player;
use function count;
use function hash;
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

			// Fire on_break hooks with the breaking player as source
			$customBlock = $manager->getCustomBlock($block);
			if($customBlock !== null){
				$hooks = $customBlock->getProperties()->getOnBreakHooks();
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
	 * Fired when a player right-clicks with an item (PlayerItemUseEvent).
	 * Triggers on_use hooks defined in the item's config.
	 */
	public function onPlayerItemUse(PlayerItemUseEvent $event) : void{
		$item = $event->getItem();

		// Custom items carry their properties via CustomItem
		if(!($item instanceof CustomItem)){
			return;
		}
		$props = $item->getProperties();
		$hooks = $props->getOnUseHooks();
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
		if(!($item instanceof CustomItem)){
			return;
		}

		$props = $item->getProperties();
		$hooks = $props->getOnAttackHooks();
		if(count($hooks) === 0){
			return;
		}

		EventHookParser::execute($hooks, $attacker, $event->getEntity());
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		// reserved for future use
	}
}
