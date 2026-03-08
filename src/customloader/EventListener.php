<?php

declare(strict_types=1);

namespace customloader;

use customloader\manager\CustomBlockManager;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
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
			foreach($customDrops as $drop){
				$block->getPosition()->getWorld()->dropItem($block->getPosition()->add(0.5, 0.5, 0.5), $drop);
			}
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		// reserved for future use
	}
}
