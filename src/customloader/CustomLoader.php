<?php

declare(strict_types=1);

namespace customloader;

use customloader\command\CustomLoaderCommand;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomEntityManager;
use customloader\manager\CustomItemManager;
use customloader\task\BlockRegistrationTask;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use function is_dir;
use function mkdir;
use function sprintf;

class CustomLoader extends PluginBase{
	use SingletonTrait;

	public function onLoad() : void{
		self::setInstance($this);
	}

	public function onEnable() : void{
		$this->saveDefaultConfig();

		if(!is_dir($this->getResourcePackFolder()) && !mkdir($dir = $this->getResourcePackFolder()) && !is_dir($dir)){
			throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
		}

		try{
			CustomItemManager::getInstance()->registerDefaultItems($this->getConfig()->get("items", []));
		}catch(\Throwable $e){
			$this->getLogger()->critical("Failed to load custom items: " . $e->getMessage());
			$this->getLogger()->logException($e);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		try{
			CustomBlockManager::getInstance()->registerDefaultBlocks($this->getConfig()->get("blocks", []));
		}catch(\Throwable $e){
			$this->getLogger()->critical("Failed to load custom blocks: " . $e->getMessage());
			$this->getLogger()->logException($e);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		// Sync custom block state handlers to async worker threads (for correct chunk save/load)
		$rawConfigs = CustomBlockManager::getInstance()->getRawConfigs();
		if(count($rawConfigs) > 0){
			$this->getServer()->getAsyncPool()->addWorkerStartHook(static function(int $worker) use ($rawConfigs) : void{
				// submit a task to each new worker to register block state handlers
				// Note: we call submitTaskToWorker from within the hook — use global access
				\pocketmine\Server::getInstance()->getAsyncPool()->submitTaskToWorker(
					new BlockRegistrationTask($rawConfigs),
					$worker
				);
			});
		}

		try{
			CustomEntityManager::getInstance()->registerDefaultEntities($this->getConfig()->get("entities", []));
		}catch(\Throwable $e){
			$this->getLogger()->critical("Failed to load custom entities: " . $e->getMessage());
			$this->getLogger()->logException($e);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

		$this->getServer()->getCommandMap()->registerAll("customloader", [
			new CustomLoaderCommand()
		]);
	}

	public function getResourcePackFolder() : string{
		return Path::join($this->getDataFolder(), "resource_packs");
	}
}
