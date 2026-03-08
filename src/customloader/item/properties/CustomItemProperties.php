<?php

declare(strict_types=1);

namespace customloader\item\properties;

use customloader\item\properties\component\ArmorComponent;
use customloader\item\properties\component\Component;
use customloader\item\properties\component\CooldownComponent;
use customloader\item\properties\component\DiggerComponent;
use customloader\item\properties\component\DisplayNameComponent;
use customloader\item\properties\component\DurableComponent;
use customloader\item\properties\component\FoodComponent;
use customloader\item\properties\component\IdentifierComponent;
use customloader\item\properties\component\ItemPropertiesComponent;
use InvalidArgumentException;
use pocketmine\block\BlockToolType;
use pocketmine\data\bedrock\item\upgrade\LegacyItemIdToStringIdMap;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use function is_numeric;

final class CustomItemProperties{
	protected string $name;
	protected int $id;
	protected string $namespace;
	protected string $texture;
	protected int $runtimeId;
	protected bool $durable = false;
	protected ?int $max_durability = null;
	protected bool $allow_off_hand = false;
	protected bool $can_destroy_in_creative = false;
	protected int $creative_category = 1;
	protected bool $hand_equipped = true;
	protected int $max_stack_size = 64;
	protected float $mining_speed = 1;
	protected bool $food = false;
	protected bool $can_always_eat = false;
	protected ?int $nutrition = null;
	protected ?float $saturation = null;
	protected ?Item $residue = null;
	protected bool $armor = false;
	protected int $defence_points;
	protected bool $tool = false;
	protected int $toolType = BlockToolType::NONE;
	protected int $toolTier = 0;
	protected bool $add_creative_inventory = false;
	protected int $attack_points = 0;
	protected int $foil;
	protected int $armorSlot = ArmorInventory::SLOT_HEAD;
	private int $cooldown = 0;

	/** @var Component[] */
	private array $components = [];
	private CompoundTag $rootNBT;

	public function __construct(string $name, array $data){
		$this->name = $name;
		$this->parseData($data);
	}

	private function parseData(array $data) : void{
		if(!isset($data["namespace"])){
			throw new InvalidArgumentException("namespace is required");
		}
		if(!isset($data["texture"])){
			throw new InvalidArgumentException("texture is required");
		}
		$this->rootNBT = CompoundTag::create()
			->setTag(Component::TAG_COMPONENTS, CompoundTag::create());

		$id = ItemTypeIds::newId();
		$namespace = (string) $data["namespace"];
		$runtimeId = $id + ($id > 0 ? 5000 : -5000);

		$this->id = $id;
		$this->runtimeId = $runtimeId;
		$this->namespace = $namespace;
		$this->texture = (string) $data["texture"];

		$this->addComponent(new IdentifierComponent($runtimeId));
		$this->addComponent(new DisplayNameComponent($this->name));

		$itemPropertiesComponent = new ItemPropertiesComponent();
		$itemPropertiesComponent->setIcon($data["texture"], $namespace);
		$itemPropertiesComponent->addComponent(ItemPropertiesComponent::TAG_USE_DURATION, new IntTag(0));

		$trueTag = new ByteTag(1);

		if(isset($data["allow_off_hand"]) && $data["allow_off_hand"] === true){
			$itemPropertiesComponent->addComponent("allow_off_hand", $trueTag);
		}
		if(isset($data["can_destroy_in_creative"]) && $data["can_destroy_in_creative"] === true){
			$itemPropertiesComponent->addComponent("can_destroy_in_creative", $trueTag);
		}
		if(isset($data["creative_category"])){
			$itemPropertiesComponent->addComponent("creative_category", new IntTag($data["creative_category"]));
		}
		if(isset($data["creative_group"])){
			$itemPropertiesComponent->addComponent("creative_group", new StringTag($data["creative_group"]));
		}
		if(isset($data["hand_equipped"])){
			$itemPropertiesComponent->addComponent("hand_equipped", $trueTag);
		}
		if(isset($data["max_stack_size"])){
			$this->max_stack_size = (int) $data["max_stack_size"];
			$itemPropertiesComponent->addComponent("max_stack_size", new IntTag($data["max_stack_size"]));
		}
		if(isset($data["food"]) && $data["food"] === true){
			if(!isset($data["nutrition"]) || !isset($data["saturation"]) || !isset($data["can_always_eat"])){
				throw new InvalidArgumentException("Food item must have nutrition, saturation, and can_always_eat");
			}
			$this->food = true;
			$this->nutrition = (int) $data["nutrition"];
			$this->saturation = (float) $data["saturation"];
			$this->can_always_eat = (bool) $data["can_always_eat"];
			$this->addComponent(new FoodComponent($this->can_always_eat, $this->nutrition, $this->saturation));
		}
		if(isset($data["armor"]) && $data["armor"]){
			if(!isset($data["defence_points"]) || !isset($data["armor_slot"]) || !isset($data["armor_class"])){
				throw new InvalidArgumentException("Armor item must have defence_points, armor_slot, and armor_class");
			}
			$this->defence_points = (int) $data["defence_points"];
			$armor_slot_int = match ($data["armor_slot"]) {
				"helmet" => ArmorInventory::SLOT_HEAD,
				"chest" => ArmorInventory::SLOT_CHEST,
				"leggings" => ArmorInventory::SLOT_LEGS,
				"boots" => ArmorInventory::SLOT_FEET,
				default => throw new InvalidArgumentException("Unknown armor slot {$data["armor_slot"]} given.")
			};
			$this->armorSlot = $armor_slot_int;
			$this->addComponent(new ArmorComponent($data["armor_class"], $armor_slot_int, $this->getDefencePoints()));
			$this->armor = true;
		}
		if(isset($data["foil"])){
			$itemPropertiesComponent->addComponent("foil", $trueTag);
		}
		if(isset($data["add_creative_inventory"])){
			$this->add_creative_inventory = (bool) $data["add_creative_inventory"];
		}
		if(isset($data["attack_points"])){
			$this->attack_points = (int) $data["attack_points"];
		}
		if(isset($data["tool"])){
			if(!isset($data["tool_type"]) || !isset($data["tool_tier"])){
				throw new InvalidArgumentException("Tool item must have tool_type and tool_tier");
			}
			$this->tool = (bool) $data["tool"];
			$this->toolType = (int) $data["tool_type"];
			$this->toolTier = (int) $data["tool_tier"];
		}
		if(isset($data["durable"])){
			if(!isset($data["max_durability"])){
				throw new InvalidArgumentException("Durable item must have max_durability");
			}
			$this->durable = true;
			$this->max_durability = (int) $data["max_durability"];
			$this->addComponent(new DurableComponent($this->max_durability));
		}
		if(isset($data["cooldown"]) && is_numeric($data["cooldown"])){
			$this->cooldown = (int) $data["cooldown"];
			$this->addComponent(new CooldownComponent($this->cooldown));
		}
		if(isset($data["dig"])){
			if(!isset($data["dig"]["block_tags"]) || !isset($data["dig"]["speed"])){
				throw new InvalidArgumentException("Property 'dig' must have block_tags and speed");
			}
			$this->addComponent(new DiggerComponent((int) $data["dig"]["speed"], $data["dig"]["block_tags"]));
			$this->mining_speed = (float) $data["dig"]["speed"];
		}

		$this->addComponent($itemPropertiesComponent);

		$legacyId = $data["id"] ?? -1;
		if($legacyId !== -1){
			LegacyItemIdToStringIdMap::getInstance()->add($this->namespace, $legacyId);
		}
	}

	public function addComponent(Component $component) : void{
		$this->components[$component->getName()] = $component;
		$component->buildComponent($this->rootNBT);
		$component->processComponent($this->rootNBT);
	}

	public function getName() : string{ return $this->name; }
	public function getNamespace() : string{ return $this->namespace; }
	public function getTexture() : string{ return $this->texture; }
	public function getId() : int{ return $this->id; }
	public function getRuntimeId() : int{ return $this->runtimeId; }
	public function getAllowOffhand() : bool{ return $this->allow_off_hand; }
	public function getCanDestroyInCreative() : bool{ return $this->can_destroy_in_creative; }
	public function getCreativeCategory() : int{ return $this->creative_category; }
	public function getHandEquipped() : bool{ return $this->hand_equipped; }
	public function getMaxStackSize() : int{ return $this->max_stack_size; }
	public function getMiningSpeed() : float{ return $this->mining_speed; }
	public function isFood() : bool{ return $this->food; }
	public function getNutrition() : ?int{ return $this->nutrition; }
	public function getSaturation() : ?float{ return $this->saturation; }
	public function getCanAlwaysEat() : bool{ return $this->can_always_eat; }
	public function getResidue() : ?Item{ return $this->residue; }
	public function isDurable() : bool{ return $this->durable; }
	public function getMaxDurability() : int{ return $this->max_durability ?? 64; }
	public function isArmor() : bool{ return $this->armor; }
	public function getDefencePoints() : int{ return $this->defence_points; }
	public function getBlockToolType() : int{ return $this->toolType; }
	public function getBlockToolHarvestLevel() : int{ return $this->toolTier; }
	public function isTool() : bool{ return $this->tool; }
	public function getAddCreativeInventory() : bool{ return $this->add_creative_inventory; }
	public function getAttackPoints() : int{ return $this->attack_points; }
	public function getArmorSlot() : int{ return $this->armorSlot; }
	public function getCooldown() : int{ return $this->cooldown; }
	public function getFoil() : bool{ return $this->foil === 1; }
	public function setBlockToolType(int $toolType) : void{ $this->toolType = $toolType; }
	public function setBlockToolHarvestLevel(int $toolTier) : void{ $this->toolTier = $toolTier; }
	public function setTool(bool $tool) : void{ $this->tool = $tool; }
	public function setAddCreativeInventory(bool $add_creative_inventory) : void{ $this->add_creative_inventory = $add_creative_inventory; }
	public function setAttackPoints(int $attack_points) : void{ $this->attack_points = $attack_points; }

	public function getNbt(bool $rebuild = false) : CompoundTag{
		if($rebuild){
			$this->rootNBT = CompoundTag::create()
				->setTag(Component::TAG_COMPONENTS, CompoundTag::create());
			$components = $this->components;
			$this->components = [];
			foreach($components as $name => $component){
				$this->addComponent($component);
			}
		}
		return $this->rootNBT;
	}
}
