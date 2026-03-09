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

	/** @var CustomBlockInterface[] All registered custom blocks (cubes, slabs, stairs) */
	private array $registered = [];
	/** @var BlockPaletteEntry[] */
	private array $paletteEntries = [];
	/** @var array<string, array{name: string, data: array<string, mixed>}> namespace => raw config */
	private array $rawConfigs = [];

	public function __construct(){}

	public function getBlocks() : array{ return $this->registered; }

	/** @return BlockPaletteEntry[] */
	public function getPaletteEntries() : array{ return $this->paletteEntries; }

	/** @return array<string, array{name: string, data: array<string, mixed>}> */
	public function getRawConfigs() : array{ return $this->rawConfigs; }

	public function isCustomBlock(Block $block) : bool{
		foreach($this->registered as $other){
			// CustomBlockInterface objects are also Blocks, so getTypeId() is safe
			if($block->getTypeId() === ($other instanceof Block ? $other->getTypeId() : -1)){
				return true;
			}
		}
		return false;
	}

	/** @return Item[] */
	public function getDrops(Block $block) : array{
		foreach($this->registered as $customBlock){
			if(!($customBlock instanceof Block)){
				continue;
			}
			if($block->getTypeId() === $customBlock->getTypeId()){
				$props = $customBlock->getProperties();
				// Prefer loot table drops when a loot table is configured
				$lootTable = $props->getLootTable();
				if($lootTable !== null){
					return LootTableManager::getInstance()->roll($lootTable);
				}
				return $props->getDropItems();
			}
		}
		return [];
	}

	/** Returns the CustomBlockInterface instance for a given block's typeId, or null. */
	public function getCustomBlock(Block $block) : ?CustomBlockInterface{
		foreach($this->registered as $customBlock){
			if($customBlock instanceof Block && $block->getTypeId() === $customBlock->getTypeId()){
				return $customBlock;
			}
		}
		return null;
	}

	/**
	 * Registers any block that implements CustomBlockInterface.
	 * Accepts CustomBlock (cube), CustomSlabBlock, and CustomStairBlock.
	 */
	public function registerBlock(CustomBlockInterface $block) : void{
		try{
			$this->registered[] = $block;
			/** @var Block $block */
			$this->internalRegisterBlock($block, $block->getProperties()->getNamespace());
		}catch(Throwable $e){
			throw new \InvalidArgumentException("Failed to register block: " . $e->getMessage(), 0, $e);
		}
	}

	public function registerDefaultBlocks(array $data) : void{
		foreach($data as $name => $blockData){
			$block = self::getBlock((string) $name, $blockData);
			$namespace = $block->getProperties()->getNamespace();
			$this->rawConfigs[$namespace] = ["name" => (string) $name, "data" => $blockData, "typeId" => $block->getProperties()->getTypeId()];
			$this->registerBlock($block);
		}
	}

	public static function getBlock(string $name, array $data, ?int $presetTypeId = null) : CustomBlockInterface{
		$props = new CustomBlockProperties($name, $data, $presetTypeId);
		$breakInfo = new BlockBreakInfo(
			$props->getHardness(),
			$props->getToolType(),
			$props->getToolTier(),
			$props->getBlastResistance()
		);
		$typeInfo = new BlockTypeInfo($breakInfo);
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
