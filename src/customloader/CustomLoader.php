<?php

declare(strict_types=1);

namespace customloader;

use customloader\command\CustomLoaderCommand;
use customloader\item\CustomItemInterface;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomEntityManager;
use customloader\manager\CustomItemManager;
use customloader\manager\CustomParticleManager;
use customloader\manager\CustomSoundManager;
use customloader\manager\LootTableManager;
use customloader\recipe\CustomRecipeManager;
use customloader\task\ArmorWearTask;
use customloader\task\BlockRegistrationTask;
use customloader\task\EntitySpawnTask;
use customloader\task\HoldingItemTask;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use RuntimeException;
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

		// ── 커스텀 아이템 ─────────────────────────────────────────────────────
		$itemErrors = CustomItemManager::getInstance()->registerDefaultItems(
			$this->getConfig()->get("items", [])
		);
		foreach($itemErrors as $name => $msg){
			$this->getLogger()->error("[items.$name] $msg");
		}
		if(count($itemErrors) > 0){
			$this->getLogger()->warning(count($itemErrors) . " item(s) failed to load. Check errors above.");
		}

		// ── 커스텀 블록 ──────────────────────────────────────────────────────
		$blockErrors = CustomBlockManager::getInstance()->registerDefaultBlocks(
			$this->getConfig()->get("blocks", [])
		);
		foreach($blockErrors as $name => $msg){
			$this->getLogger()->error("[blocks.$name] $msg");
		}
		if(count($blockErrors) > 0){
			$this->getLogger()->warning(count($blockErrors) . " block(s) failed to load. Check errors above.");
		}

		// ── 비동기 워커에 블록 스테이트 핸들러 동기화 ──────────────────────────
		$rawConfigs = CustomBlockManager::getInstance()->getRawConfigs();
		if(count($rawConfigs) > 0){
			$this->getServer()->getAsyncPool()->addWorkerStartHook(static function(int $worker) use ($rawConfigs) : void{
				\pocketmine\Server::getInstance()->getAsyncPool()->submitTaskToWorker(
					new BlockRegistrationTask($rawConfigs),
					$worker
				);
			});
		}

		// ── 커스텀 엔티티 ─────────────────────────────────────────────────────
		$entityErrors = CustomEntityManager::getInstance()->registerDefaultEntities(
			$this->getConfig()->get("entities", [])
		);
		foreach($entityErrors as $name => $msg){
			$this->getLogger()->error("[entities.$name] $msg");
		}
		if(count($entityErrors) > 0){
			$this->getLogger()->warning(count($entityErrors) . " entity(ies) failed to load. Check errors above.");
		}

		// ── 루트 테이블 ─────────────────────────────────────────────────────
		try{
			LootTableManager::getInstance()->registerDefaultLootTables(
				$this->getConfig()->get("loot_tables", [])
			);
			$tableCount = LootTableManager::getInstance()->getTableCount();
			if($tableCount > 0){
				$this->getLogger()->info("Loaded $tableCount loot table(s).");
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
			$recipeCount = CustomRecipeManager::getInstance()->getRecipeCount();
			if($recipeCount > 0){
				$this->getLogger()->info("Loaded $recipeCount custom recipe(s).");
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

		// ── 성능 설정 읽기 ───────────────────────────────────────────────────
		$perf          = (array) $this->getConfig()->get("performance", []);
		$spawnInterval = max(20, (int) ($perf["entity_spawn_interval"] ?? 400));
		$disabledWorlds = (array) ($perf["particle_disabled_worlds"] ?? []);

		// ── 스폰 규칙 스케줄러 ───────────────────────────────────────────────
		$spawnRules = CustomEntityManager::getInstance()->getSpawnRules();
		if(($perf["entity_spawn"] ?? true) && count($spawnRules) > 0){
			$this->getScheduler()->scheduleRepeatingTask(
				new EntitySpawnTask($spawnRules),
				$spawnInterval
			);
			$this->getLogger()->info("Registered " . count($spawnRules) . " entity spawn rule(s).");
		}

		// ── hold_particle / wear_particle 스케줄러 ───────────────────────────
		$hasHoldParticle = false;
		$hasWearParticle = false;
		foreach(CustomItemManager::getInstance()->getItems() as $item){
			if(!($item instanceof CustomItemInterface)){
				continue;
			}
			$props = $item->getProperties();
			if($props->getHoldParticle() !== "") $hasHoldParticle = true;
			if($props->getWearParticle() !== "") $hasWearParticle = true;
			if($hasHoldParticle && $hasWearParticle) break;
		}
		if(($perf["hold_particle"] ?? true) && $hasHoldParticle){
			$this->getScheduler()->scheduleRepeatingTask(new HoldingItemTask($disabledWorlds), 1);
		}
		if(($perf["wear_particle"] ?? true) && $hasWearParticle){
			$this->getScheduler()->scheduleRepeatingTask(new ArmorWearTask($disabledWorlds), 1);
		}

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

		$this->getServer()->getCommandMap()->registerAll("customloader", [
			new CustomLoaderCommand()
		]);
	}

	public function getResourcePackFolder() : string{
		return $this->getDataFolder() . "resource_packs";
	}
}
