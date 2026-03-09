<?php

declare(strict_types=1);

namespace customloader\api;

use customloader\block\CustomBlockInterface;
use customloader\event\hook\EventHookParser;
use customloader\item\CustomItemInterface;
use customloader\item\properties\CustomItemProperties;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomEntityManager;
use customloader\manager\CustomItemManager;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\item\Item;

/**
 * CustomLoader Public API — 다른 플러그인에서 커스텀 콘텐츠에 쉽게 접근하기 위한 파사드.
 *
 * 사용 예시:
 *
 *   // 커스텀 아이템 조회
 *   $item = CustomLoaderAPI::getItem("mypack:ruby_sword");
 *
 *   // 현재 들고 있는 아이템이 커스텀 아이템인지 확인
 *   if(CustomLoaderAPI::isCustomItem($player->getInventory()->getItemInHand())){
 *       ...
 *   }
 *
 *   // 커스텀 블록 확인
 *   if(CustomLoaderAPI::isCustomBlock($block)){
 *       $ns = CustomLoaderAPI::getBlockNamespace($block);
 *   }
 *
 *   // 훅 직접 실행 (PHP 코드에서 직접 액션 트리거)
 *   CustomLoaderAPI::executeActions([
 *       ["action" => "give_effect", "effect" => "speed", "duration" => 100, "amplifier" => 0],
 *   ], $player);
 */
final class CustomLoaderAPI{

	private function __construct(){} // static utility only

	// ── 아이템 ──────────────────────────────────────────────────────────────

	/**
	 * namespace로 커스텀 아이템 인스턴스를 가져옵니다.
	 * 없으면 null 반환.
	 *
	 * @example
	 * $item = CustomLoaderAPI::getItem("mypack:ruby_sword");
	 */
	public static function getItem(string $namespace) : ?Item{
		return CustomItemManager::getInstance()->getItemByNamespace($namespace);
	}

	/**
	 * 해당 Item이 CustomLoader로 등록된 커스텀 아이템인지 확인합니다.
	 */
	public static function isCustomItem(Item $item) : bool{
		return $item instanceof CustomItemInterface;
	}

	/**
	 * 커스텀 아이템의 Properties를 가져옵니다.
	 * 커스텀 아이템이 아니면 null 반환.
	 */
	public static function getItemProperties(Item $item) : ?CustomItemProperties{
		if($item instanceof CustomItemInterface){
			return $item->getProperties();
		}
		return null;
	}

	/**
	 * 커스텀 아이템의 namespace를 가져옵니다.
	 * 커스텀 아이템이 아니면 null 반환.
	 */
	public static function getItemNamespace(Item $item) : ?string{
		return self::getItemProperties($item)?->getNamespace();
	}

	// ── 블록 ──────────────────────────────────────────────────────────────

	/**
	 * namespace로 커스텀 블록 인스턴스를 가져옵니다.
	 */
	public static function getBlock(string $namespace) : ?CustomBlockInterface{
		return CustomBlockManager::getInstance()->getCustomBlockByNamespace($namespace);
	}

	/**
	 * 해당 Block이 CustomLoader로 등록된 커스텀 블록인지 확인합니다.
	 */
	public static function isCustomBlock(Block $block) : bool{
		return CustomBlockManager::getInstance()->isCustomBlock($block);
	}

	/**
	 * 커스텀 블록의 namespace를 가져옵니다.
	 * 커스텀 블록이 아니면 null 반환.
	 */
	public static function getBlockNamespace(Block $block) : ?string{
		return CustomBlockManager::getInstance()->getCustomBlock($block)?->getProperties()->getNamespace();
	}

	// ── 엔티티 ──────────────────────────────────────────────────────────────

	/**
	 * 등록된 커스텀 엔티티 namespace 목록을 반환합니다.
	 *
	 * @return string[]
	 */
	public static function getRegisteredEntityNamespaces() : array{
		return array_keys(CustomEntityManager::getInstance()->getRegisteredEntities());
	}

	// ── 훅 / 액션 ──────────────────────────────────────────────────────────

	/**
	 * 파싱된 EventAction 배열을 실행합니다.
	 * EventHookParser::parse()로 파싱한 결과를 넘기세요.
	 *
	 * @param \customloader\event\hook\EventAction[] $actions
	 * @example
	 * $actions = EventHookParser::parse([
	 *     ["action" => "give_effect", "effect" => "speed", "duration" => 100, "amplifier" => 1],
	 *     ["action" => "play_sound",  "sound"  => "random.orb"],
	 * ]);
	 * CustomLoaderAPI::executeActions($actions, $player);
	 */
	public static function executeActions(array $actions, Entity $source, ?Entity $target = null) : void{
		EventHookParser::execute($actions, $source, $target);
	}

	/**
	 * YAML 액션 데이터 배열을 파싱하고 즉시 실행합니다.
	 * 파싱과 실행을 한 번에 처리하는 편의 메서드.
	 *
	 * @param array<int, array<string, mixed>> $rawActions
	 * @example
	 * CustomLoaderAPI::parseAndExecute([
	 *     ["action" => "set_health", "amount" => 10.0, "mode" => "add"],
	 *     ["action" => "give_xp",   "amount" => 5],
	 * ], $player);
	 */
	public static function parseAndExecute(array $rawActions, Entity $source, ?Entity $target = null) : void{
		$actions = EventHookParser::parse($rawActions);
		EventHookParser::execute($actions, $source, $target);
	}
}
