<?php

declare(strict_types=1);

namespace customloader;

use customloader\command\CustomLoaderCommand;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomEntityManager;
use customloader\manager\CustomItemManager;
use customloader\manager\CustomParticleManager;
use customloader\manager\CustomSoundManager;
use customloader\manager\LootTableManager;
use customloader\recipe\CustomRecipeManager;
use customloader\task\BlockRegistrationTask;
use customloader\task\EntitySpawnTask;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use function count;
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

		// ── 루트 테이블 ─────────────────────────────────────────────────────
		try{
			LootTableManager::getInstance()->registerDefaultLootTables(
				$this->getConfig()->get("loot_tables", [])
			);
			if(LootTableManager::getInstance()->getTableCount() > 0){
				$this->getLogger()->info("Loaded " . LootTableManager::getInstance()->getTableCount() . " loot table(s).");
			}
		}catch(\Throwable $e){
			$this->getLogger()->warning("Failed to load loot tables: " . $e->getMessage());
		}

		// ── 커스텀 레시피 ────────────────────────────────────────────────────
		try{
			CustomRecipeManager::getInstance()->registerDefaultRecipes(
				$this->getConfig()->get("recipes", []),
				$this->getServer()->getCraftingManager()
			);
			if(CustomRecipeManager::getInstance()->getRecipeCount() > 0){
				$this->getLogger()->info("Loaded " . CustomRecipeManager::getInstance()->getRecipeCount() . " custom recipe(s).");
			}
		}catch(\Throwable $e){
			$this->getLogger()->warning("Failed to load recipes: " . $e->getMessage());
		}

		// ── 커스텀 사운드 ────────────────────────────────────────────────────
		try{
			CustomSoundManager::getInstance()->registerDefaultSounds(
				$this->getConfig()->get("sounds", [])
			);
		}catch(\Throwable $e){
			$this->getLogger()->warning("Failed to load custom sounds: " . $e->getMessage());
		}

		// ── 커스텀 파티클 ────────────────────────────────────────────────────
		try{
			CustomParticleManager::getInstance()->registerDefaultParticles(
				$this->getConfig()->get("particles", [])
			);
		}catch(\Throwable $e){
			$this->getLogger()->warning("Failed to load custom particles: " . $e->getMessage());
		}

		// ── 스폰 규칙 스케줄러 ───────────────────────────────────────────────
		$spawnRules = CustomEntityManager::getInstance()->getSpawnRules();
		if(count($spawnRules) > 0){
			$this->getScheduler()->scheduleRepeatingTask(
				new EntitySpawnTask($spawnRules),
				400 // every 20 seconds
			);
			$this->getLogger()->info("Registered " . count($spawnRules) . " entity spawn rule(s).");
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
