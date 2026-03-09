<?php

declare(strict_types=1);

namespace customloader\item\properties;

use customloader\event\hook\EventAction;
use customloader\event\hook\EventHookParser;
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
use function is_array;
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

	/** @var EventAction[]  Actions fired when the player uses (right-clicks) this item */
	private array $onUseHooks = [];
	/** @var EventAction[]  Shift+우클릭 시 실행 */
	private array $onSneakUseHooks = [];
	/** @var EventAction[]  Actions fired when the player attacks an entity with this item */
	private array $onAttackHooks = [];
	/** @var EventAction[]  Shift+공격 시 실행 */
	private array $onSneakAttackHooks = [];
	/** @var EventAction[]  Actions fired when the player eats this item (food only) */
	private array $onEatHooks = [];
	/** Furnace burn time in ticks (0 = not a fuel). Wood = 300 ticks */
	private int $fuelBurnTime = 0;

	// ── 애니메이션 / 이펙트 ───────────────────────────────────────────────────
	/** 들고 있을 때 서버가 주기적으로 스폰하는 파티클 namespace */
	private string $holdParticle = "";
	/** 파티클 스폰 주기 (틱, 기본 20 = 1초) */
	private int $holdParticleInterval = 20;
	/** 공격 시 AnimatePacket 타입: "crit" | "magic_crit" | "" */
	private string $attackAnimate = "";
	/** RP attachable: 들고 있을 때 재생할 애니메이션 ID */
	private string $holdAnimation = "";
	/** RP attachable: 공격(is_attacking) 시 재생할 애니메이션 ID */
	private string $attackAnimation = "";
	/** RP attachable: 우클릭(is_using_item) 시 재생할 애니메이션 ID */
	private string $useAnimation = "";
	/** RP attachable: shift+우클릭(is_sneaking && is_using_item) 시 재생 */
	private string $sneakUseAnimation = "";
	/** 방어구 착용 중 서버가 주기적으로 스폰하는 파티클 namespace */
	private string $wearParticle = "";
	/** 방어구 파티클 스폰 주기 (틱, 기본 20 = 1초) */
	private int $wearParticleInterval = 20;
	/** RP armor attachable: 착용 중 루프 재생 애니메이션 ID */
	private string $wearAnimation = "";

	/**
	 * 통합 애니메이션 맵: shortName → animId
	 * 삽입 순서 = scripts.animate 순서 (config에 적힌 순서 = 재생 우선순위)
	 * 지원 키: hold, attack, use, sneak_use, sneak, sprint, jump, fall, swim, walk, idle, wear
	 * @var array<string, string>
	 */
	private array $animations = [];

	/** @var EventAction[]  점프 시 실행 훅 */
	private array $onJumpHooks = [];
	/** @var EventAction[]  달리기 시작 시 실행 훅 */
	private array $onSprintHooks = [];
	/** @var EventAction[]  스니크 시작 시 실행 훅 */
	private array $onSneakStartHooks = [];

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

		// Furnace fuel
		if(isset($data["fuel"]) && is_array($data["fuel"])){
			$this->fuelBurnTime = max(0, (int) ($data["fuel"]["burn_time"] ?? 0));
		}elseif(isset($data["fuel"])){
			$this->fuelBurnTime = max(0, (int) $data["fuel"]);
		}

		// Animation / visual effects
		$this->holdParticle         = (string) ($data["hold_particle"] ?? "");
		$this->holdParticleInterval = max(1, (int) ($data["hold_particle_interval"] ?? 20));
		$this->attackAnimate        = (string) ($data["attack_animate"] ?? "");
		$this->holdAnimation        = (string) ($data["hold_animation"] ?? "");
		$this->attackAnimation      = (string) ($data["attack_animation"] ?? "");
		$this->useAnimation         = (string) ($data["use_animation"] ?? "");
		$this->sneakUseAnimation    = (string) ($data["sneak_use_animation"] ?? "");
		$this->wearParticle         = (string) ($data["wear_particle"] ?? "");
		$this->wearParticleInterval = max(1, (int) ($data["wear_particle_interval"] ?? 20));
		$this->wearAnimation        = (string) ($data["wear_animation"] ?? "");

		// Event hooks — parsed lazily so unknown action types are skipped silently
		if(isset($data["on_use"]) && is_array($data["on_use"])){
			$this->onUseHooks = EventHookParser::parse($data["on_use"]);
		}
		if(isset($data["on_sneak_use"]) && is_array($data["on_sneak_use"])){
			$this->onSneakUseHooks = EventHookParser::parse($data["on_sneak_use"]);
		}
		if(isset($data["on_attack"]) && is_array($data["on_attack"])){
			$this->onAttackHooks = EventHookParser::parse($data["on_attack"]);
		}
		if(isset($data["on_sneak_attack"]) && is_array($data["on_sneak_attack"])){
			$this->onSneakAttackHooks = EventHookParser::parse($data["on_sneak_attack"]);
		}
		if(isset($data["on_eat"]) && is_array($data["on_eat"])){
			$this->onEatHooks = EventHookParser::parse($data["on_eat"]);
		}

		// ── 통합 animations 맵 구성 ──────────────────────────────────────────
		// 1단계: 개별 필드를 고정 순서로 삽입 (하위 호환)
		if($this->holdAnimation !== "")     $this->animations["hold"]      = $this->holdAnimation;
		if($this->attackAnimation !== "")   $this->animations["attack"]    = $this->attackAnimation;
		if($this->useAnimation !== "")      $this->animations["use"]       = $this->useAnimation;
		if($this->sneakUseAnimation !== "") $this->animations["sneak_use"] = $this->sneakUseAnimation;
		if($this->wearAnimation !== "")     $this->animations["wear"]      = $this->wearAnimation;

		// 2단계: 명시적 animations: 맵 (덮어쓰기 + 확장; 키 순서 = 재생 우선순위)
		if(isset($data["animations"]) && is_array($data["animations"])){
			foreach($data["animations"] as $key => $animId){
				if(is_string($animId) && $animId !== ""){
					$this->animations[(string) $key] = $animId;
				}
			}
		}

		// 새 훅: on_jump / on_sprint / on_sneak
		if(isset($data["on_jump"]) && is_array($data["on_jump"])){
			$this->onJumpHooks = EventHookParser::parse($data["on_jump"]);
		}
		if(isset($data["on_sprint"]) && is_array($data["on_sprint"])){
			$this->onSprintHooks = EventHookParser::parse($data["on_sprint"]);
		}
		if(isset($data["on_sneak"]) && is_array($data["on_sneak"])){
			$this->onSneakStartHooks = EventHookParser::parse($data["on_sneak"]);
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

	/** @return EventAction[] */
	public function getOnUseHooks() : array{ return $this->onUseHooks; }

	/** @return EventAction[] */
	public function getOnAttackHooks() : array{ return $this->onAttackHooks; }

	/** @return EventAction[]  Actions executed when a player eats this item */
	public function getOnEatHooks() : array{ return $this->onEatHooks; }

	/** Returns furnace burn time in ticks (0 = not a fuel). Vanilla wood = 300 */
	public function getFuelBurnTime() : int{ return $this->fuelBurnTime; }

	/** Returns true if this item can be used as furnace fuel */
	public function isFuel() : bool{ return $this->fuelBurnTime > 0; }

	// ── 애니메이션 / 이펙트 ───────────────────────────────────────────────────

	/** 들고 있을 때 주기적으로 스폰할 파티클 namespace ("" = 없음) */
	public function getHoldParticle() : string{ return $this->holdParticle; }

	/** 파티클 스폰 주기 (틱) */
	public function getHoldParticleInterval() : int{ return $this->holdParticleInterval; }

	/** 공격 시 AnimatePacket 타입 ("crit" | "magic_crit" | "" = 없음) */
	public function getAttackAnimate() : string{ return $this->attackAnimate; }

	/** RP attachable: 들고 있을 때 애니메이션 ID ("" = 없음) */
	public function getHoldAnimation() : string{ return $this->holdAnimation; }

	/** RP attachable: 공격 시 애니메이션 ID ("" = 없음) */
	public function getAttackAnimation() : string{ return $this->attackAnimation; }

	/** RP attachable: 우클릭(is_using_item) 시 애니메이션 ID ("" = 없음) */
	public function getUseAnimation() : string{ return $this->useAnimation; }

	/** RP attachable: shift+우클릭 시 애니메이션 ID ("" = 없음) */
	public function getSneakUseAnimation() : string{ return $this->sneakUseAnimation; }

	/** Shift+우클릭 훅 */
	public function getOnSneakUseHooks() : array{ return $this->onSneakUseHooks; }

	/** Shift+공격 훅 */
	public function getOnSneakAttackHooks() : array{ return $this->onSneakAttackHooks; }

	/** 방어구 착용 중 파티클 namespace ("" = 없음) */
	public function getWearParticle() : string{ return $this->wearParticle; }

	/** 방어구 파티클 주기 (틱) */
	public function getWearParticleInterval() : int{ return $this->wearParticleInterval; }

	/** RP armor attachable: 착용 중 루프 애니메이션 ID ("" = 없음) */
	public function getWearAnimation() : string{ return $this->wearAnimation; }

	/**
	 * 통합 애니메이션 맵 반환.
	 * 키 삽입 순서 = Bedrock scripts.animate 배열 순서.
	 * @return array<string, string>
	 */
	public function getAnimations() : array{ return $this->animations; }

	/** @return EventAction[]  점프 시 실행 훅 */
	public function getOnJumpHooks() : array{ return $this->onJumpHooks; }

	/** @return EventAction[]  달리기 시작 시 실행 훅 */
	public function getOnSprintHooks() : array{ return $this->onSprintHooks; }

	/** @return EventAction[]  스니크 시작 시 실행 훅 */
	public function getOnSneakStartHooks() : array{ return $this->onSneakStartHooks; }

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
