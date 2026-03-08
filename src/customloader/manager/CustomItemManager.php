<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\item\CustomArmorItem;
use customloader\item\CustomDurableItem;
use customloader\item\CustomFoodItem;
use customloader\item\CustomItem;
use customloader\item\CustomItemTrait;
use customloader\item\CustomToolItem;
use customloader\item\properties\CustomItemProperties;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Throwable;
use function class_uses;
use function in_array;
use function str_replace;
use function strtolower;

final class CustomItemManager{
	use SingletonTrait;

	/** @var Item[] */
	private array $registered = [];
	/** @var ItemTypeEntry[] */
	private array $itemTypeEntries = [];

	public function __construct(){}

	public function getItems() : array{ return $this->registered; }

	public function isCustomItem(Item $item) : bool{
		foreach($this->registered as $other){
			if($item->equals($other, false, false)){
				return true;
			}
		}
		return false;
	}

	/** @param CustomItemTrait|Item $item */
	public function registerItem($item) : void{
		try{
			$runtimeId = $item->getProperties()->getRuntimeId();
			$this->itemTypeEntries[] = new ItemTypeEntry(
				$item->getProperties()->getNamespace(),
				$runtimeId,
				true,
				1,
				new CacheableNbt($item->getProperties()->getNbt(true))
			);
			$this->registered[] = $item;
			$this->internalRegisterItem(clone $item, $runtimeId, true, $item->getProperties()->getNamespace());
		}catch(Throwable $e){
			throw new \InvalidArgumentException("Failed to register item: " . $e->getMessage(), $e->getLine(), $e);
		}
	}

	public function getEntries() : array{ return $this->itemTypeEntries; }

	public function registerDefaultItems(array $data) : void{
		foreach($data as $name => $itemData){
			$this->registerItem(self::getItem($name, $itemData));
		}
	}

	public static function getItem(string $name, array $data) : Item{
		$prop = new CustomItemProperties($name, $data);
		if($prop->isDurable()) return new CustomDurableItem($name, $prop);
		if($prop->isFood()) return new CustomFoodItem($name, $prop);
		if($prop->isArmor()) return new CustomArmorItem($name, $prop);
		if($prop->isTool()) return new CustomToolItem($name, $prop);
		return new CustomItem($name, $prop);
	}

	public function internalRegisterItem(Item $item, int $runtimeId, bool $force = false, string $namespace = "", ?\Closure $serializeCallback = null, ?\Closure $deserializeCallback = null) : void{
		$namespace = $namespace === "" ? "minecraft:" . strtolower(str_replace(" ", "_", $item->getName())) : $namespace;

		StringToItemParser::getInstance()->override($item->getName(), static fn() => clone $item);

		// Use public APIs instead of closure hacking for serializer/deserializer
		GlobalItemDataHandlers::getSerializer()->map(
			$item,
			$serializeCallback ?? static fn(Item $_) => new SavedItemData($namespace)
		);
		GlobalItemDataHandlers::getDeserializer()->map(
			$namespace,
			$deserializeCallback ?? static fn(SavedItemData $_) => clone $item
		);

		// ItemTypeDictionary has no public API — closure hack is still required
		$dictionary = TypeConverter::getInstance()->getItemTypeDictionary();
		(function() use ($item, $runtimeId, $namespace) : void{
			$this->stringToIntMap[$namespace] = $runtimeId;
			$this->intToStringIdMap[$runtimeId] = $namespace;
			$nbt = in_array(CustomItemTrait::class, class_uses($item), true) ? $item->getProperties()->getNbt() : CompoundTag::create();
			$this->itemTypes[] = new ItemTypeEntry($namespace, $runtimeId, true, 1, new CacheableNbt($nbt));
		})->call($dictionary);
	}
}
