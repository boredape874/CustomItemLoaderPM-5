<?php

declare(strict_types=1);

namespace customloader\task;

use customloader\block\CustomBlockInterface;
use customloader\manager\CustomBlockManager;
use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Throwable;

/**
 * Registers custom block state serializers/deserializers in async worker threads.
 * This is required for correct chunk save/load of chunks containing custom blocks.
 */
final class BlockRegistrationTask extends AsyncTask{

	/**
	 * @param array<string, array{name: string, data: array<string, mixed>, typeId: int}> $rawConfigs
	 */
	public function __construct(private array $rawConfigs){}

	public function onRun() : void{
		foreach($this->rawConfigs as $namespace => $config){
			try{
				// Reconstruct block with the pre-assigned typeId to keep consistent IDs across threads
				/** @var CustomBlockInterface&Block $block */
				$block = CustomBlockManager::getBlock(
					$config["name"],
					$config["data"],
					$config["typeId"]
				);
				// Register both serializer + deserializer via the unified registrar
				// All CustomBlockInterface implementations extend Block, so this cast is safe.
				GlobalBlockStateHandlers::getRegistrar()->mapSimple($block, $namespace);
			}catch(Throwable){
				// Silently skip — async workers should not crash the server
			}
		}
	}
}
