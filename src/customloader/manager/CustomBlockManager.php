<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\block\CustomBlock;
use customloader\block\CustomBlockInterface;
use customloader\block\CustomFenceBlock;
use customloader\block\CustomLeavesBlock;
use customloader\block\CustomSlabBlock;
use customloader\block\CustomStairBlock;
use customloader\block\properties\CustomBlockProperties;
use customloader\loot\LootTableManager;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\StringToBlockParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Throwable;

final class CustomBlockManager{
	use SingletonTrait;

	/** @var array<int, CustomBlockInterface> typeId => block (O(1) lookup) */
	private array $byTypeId = [];
	/** @var array<string, CustomBlockInterface> namespace => block (O(1) lookup) */
	private array $byNamespace = [];
	/** @var BlockPaletteEntry[] */
	private array $paletteEntries = [];
	/** @var array<string, array{name: string, data: array<string, mixed>}> namespace => raw config */
	private array $rawConfigs = [];

	public function __construct(){}

	/** @return CustomBlockInterface[] */
	public function getBlocks() : array{ return array_values($this->byTypeId); }

	/** @return BlockPaletteEntry[] */
	public function getPaletteEntries() : array{ return $this->paletteEntries; }

	/** @return array<string, array{name: string, data: array<string, mixed>}> */
	public function getRawConfigs() : array{ return $this->rawConfigs; }

	/** O(1) — 등록된 커스텀 블록인지 확인 */
	public function isCustomBlock(Block $block) : bool{
		return isset($this->byTypeId[$block->getTypeId()]);
	}

	/** O(1) — namespace로 커스텀 블록 조회 */
	public function getCustomBlockByNamespace(string $namespace) : ?CustomBlockInterface{
		return $this->byNamespace[$namespace] ?? null;
	}

	/** O(1) — Block의 typeId로 커스텀 블록 인스턴스 반환 */
	public function getCustomBlock(Block $block) : ?CustomBlockInterface{
		return $this->byTypeId[$block->getTypeId()] ?? null;
	}

	/** @return Item[] */
	public function getDrops(Block $block) : array{
		$customBlock = $this->byTypeId[$block->getTypeId()] ?? null;
		if($customBlock === null){
			return [];
		}
		$props = $customBlock->getProperties();
		$lootTable = $props->getLootTable();
		if($lootTable !== null){
			return LootTableManager::getInstance()->roll($lootTable);
		}
		return $props->getDropItems();
	}

	/**
	 * Registers any block that implements CustomBlockInterface.
	 * Accepts CustomBlock (cube), CustomSlabBlock, CustomStairBlock, CustomFenceBlock, CustomLeavesBlock.
	 */
	public function registerBlock(CustomBlockInterface $block) : void{
		try{
			/** @var Block $block */
			$namespace = $block->getProperties()->getNamespace();
			$typeId    = $block->getTypeId();
			$this->byTypeId[$typeId]       = $block;
			$this->byNamespace[$namespace] = $block;
			$this->internalRegisterBlock($block, $namespace);
		}catch(Throwable $e){
			throw new \InvalidArgumentException("Failed to register block: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Validates config data without registering anything.
	 * Returns an array of [name => errorMessage] for invalid entries.
	 *
	 * @return array<string, string>
	 */
	public function validateConfig(array $data) : array{
		$errors = [];
		foreach($data as $name => $blockData){
			try{
				self::getBlock((string) $name, (array) $blockData);
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
	}

	/**
	 * Registers all blocks from config data.
	 * Returns an array of [name => errorMessage] for failed blocks.
	 *
	 * @return array<string, string>
	 */
	public function registerDefaultBlocks(array $data) : array{
		$errors = [];
		foreach($data as $name => $blockData){
			try{
				$block     = self::getBlock((string) $name, $blockData);
				$namespace = $block->getProperties()->getNamespace();
				$this->rawConfigs[$namespace] = [
					"name"   => (string) $name,
					"data"   => $blockData,
					"typeId" => $block->getProperties()->getTypeId(),
				];
				$this->registerBlock($block);
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
	}

	public static function getBlock(string $name, array $data, ?int $presetTypeId = null) : CustomBlockInterface{
		$props     = new CustomBlockProperties($name, $data, $presetTypeId);
		$breakInfo = new BlockBreakInfo(
			$props->getHardness(),
			$props->getToolType(),
			$props->getToolTier(),
			$props->getBlastResistance()
		);
		$typeInfo   = new BlockTypeInfo($breakInfo);
		$identifier = new BlockIdentifier($props->getTypeId());

		return match($props->getBlockType()){
			"slab"   => new CustomSlabBlock($identifier, $typeInfo, $props),
			"stair"  => new CustomStairBlock($identifier, $typeInfo, $props),
			"fence"  => new CustomFenceBlock($identifier, $typeInfo, $props),
			"leaves" => new CustomLeavesBlock($identifier, $typeInfo, $props),
			default  => new CustomBlock($identifier, $typeInfo, $props),
		};
	}

	public function internalRegisterBlock(Block $block, string $namespace) : void{
		RuntimeBlockStateRegistry::getInstance()->register($block);
		// getRegistrar()->mapSimple() registers both serializer + deserializer in one call (preferred API)
		GlobalBlockStateHandlers::getRegistrar()->mapSimple($block, $namespace);
		StringToBlockParser::getInstance()->override($block->getName(), static fn() => clone $block);
		StringToItemParser::getInstance()->registerBlock($block->getName(), static fn() => clone $block);
		$this->paletteEntries[] = new BlockPaletteEntry($namespace, new CacheableNbt(CompoundTag::create()));
	}
}
