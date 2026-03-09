<?php

declare(strict_types=1);

namespace customloader\manager;

use customloader\item\CustomArmorItem;
use customloader\item\CustomDurableItem;
use customloader\item\CustomFoodItem;
use customloader\item\CustomItem;
use customloader\item\CustomItemInterface;
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

	/** @var array<string, Item> namespace => item (O(1) lookup) */
	private array $byNamespace = [];
	/** @var ItemTypeEntry[] */
	private array $itemTypeEntries = [];

	public function __construct(){}

	/** @return Item[] */
	public function getItems() : array{ return array_values($this->byNamespace); }

	/** O(1) — namespace로 아이템 인스턴스 반환 */
	public function getItemByNamespace(string $namespace) : ?Item{
		return $this->byNamespace[$namespace] ?? null;
	}

	/** O(1) — Item이 커스텀 아이템인지 확인 */
	public function isCustomItem(Item $item) : bool{
		return $item instanceof CustomItemInterface;
	}

	/** @param CustomItemTrait|Item $item */
	public function registerItem($item) : void{
		try{
			$namespace  = $item->getProperties()->getNamespace();
			$runtimeId  = $item->getProperties()->getRuntimeId();
			$this->itemTypeEntries[] = new ItemTypeEntry(
				$namespace,
				$runtimeId,
				true,
				1,
				new CacheableNbt($item->getProperties()->getNbt(true))
			);
			$this->byNamespace[$namespace] = $item;
			$this->internalRegisterItem(clone $item, $runtimeId, true, $namespace);
		}catch(Throwable $e){
			throw new \InvalidArgumentException("Failed to register item: " . $e->getMessage(), $e->getLine(), $e);
		}
	}

	public function getEntries() : array{ return $this->itemTypeEntries; }

	/**
	 * Registers all items from config data.
	 * Returns an array of [name => errorMessage] for failed items.
	 *
	 * @return array<string, string>
	 */
	/**
	 * Validates config data without registering anything.
	 * Returns an array of [name => errorMessage] for invalid entries.
	 *
	 * @return array<string, string>
	 */
	public function validateConfig(array $data) : array{
		$errors = [];
		foreach($data as $name => $itemData){
			try{
				self::getItem((string) $name, (array) $itemData);
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
	}

	public function registerDefaultItems(array $data) : array{
		$errors = [];
		foreach($data as $name => $itemData){
			try{
				$this->registerItem(self::getItem((string) $name, $itemData));
			}catch(\Throwable $e){
				$errors[(string) $name] = $e->getMessage();
			}
		}
		return $errors;
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
