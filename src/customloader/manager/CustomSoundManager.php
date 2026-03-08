<?php

declare(strict_types=1);

namespace customloader\manager;

use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use function array_key_exists;

/**
 * Registry for custom sound definitions loaded from config.yml.
 *
 * Config format:
 * ```yaml
 * sounds:
 *   ruby_break:
 *     file: "ruby_break"       # sound file name under sounds/ in the RP (no extension)
 *     volume: 1.0
 *     pitch: 1.0
 *     category: block          # block | player | ambient | music | neutral | weather | record
 * ```
 *
 * The `category` field is stored for ResourcePackBuilder use; it is not sent
 * in PlaySoundPacket (Bedrock resolves category from sound_definitions.json).
 *
 * Usage:
 * ```php
 * CustomSoundManager::getInstance()->playAt("ruby_break", $position, $world->getPlayers());
 * ```
 */
final class CustomSoundManager{
	use SingletonTrait;

	private const VALID_CATEGORIES = [
		"block", "player", "ambient", "music", "neutral", "weather", "record",
	];

	/**
	 * @var array<string, array{file: string, volume: float, pitch: float, category: string}>
	 *             Sound name => definition
	 */
	private array $sounds = [];

	public function __construct(){}

	/**
	 * Bulk-register sounds from the config "sounds" section.
	 *
	 * @param array<string, mixed> $data Raw config array (sounds section).
	 */
	public function registerDefaultSounds(array $data) : void{
		foreach($data as $name => $def){
			$name = (string) $name;
			$def  = (array)  $def;

			if(!isset($def["file"])){
				// Skip invalid entries silently; plugin logger should warn at load time
				continue;
			}

			$this->register($name, $def);
		}
	}

	/**
	 * Register a single sound definition.
	 *
	 * @param array<string, mixed> $def
	 */
	public function register(string $name, array $def) : void{
		$category = strtolower((string) ($def["category"] ?? "neutral"));
		if(!in_array($category, self::VALID_CATEGORIES, true)){
			$category = "neutral";
		}

		$this->sounds[$name] = [
			"file"     => (string) $def["file"],
			"volume"   => max(0.0, (float) ($def["volume"] ?? 1.0)),
			"pitch"    => max(0.01, (float) ($def["pitch"]  ?? 1.0)),
			"category" => $category,
		];
	}

	/**
	 * Returns the definition for the given sound name, or null if not found.
	 *
	 * @return array{file: string, volume: float, pitch: float, category: string}|null
	 */
	public function get(string $name) : ?array{
		return $this->sounds[$name] ?? null;
	}

	public function has(string $name) : bool{
		return array_key_exists($name, $this->sounds);
	}

	/**
	 * Returns all registered sound definitions.
	 * Used by ResourcePackBuilder when generating sound_definitions.json.
	 *
	 * @return array<string, array{file: string, volume: float, pitch: float, category: string}>
	 */
	public function getAll() : array{
		return $this->sounds;
	}

	/**
	 * Plays a registered custom sound at the given position for the given players.
	 *
	 * Sends a PlaySoundPacket directly to each player's network session.
	 * No sound is played if the sound name is not registered.
	 *
	 * @param Player[] $players List of players to send the packet to.
	 */
	public function playAt(string $soundName, Position $pos, array $players) : void{
		$def = $this->get($soundName);
		if($def === null){
			return;
		}

		// PlaySoundPacket uses the file name as the sound identifier.
		// Bedrock resolves it against sound_definitions.json in the resource pack.
		$packet = PlaySoundPacket::create(
			$def["file"],
			$pos->getX(),
			$pos->getY(),
			$pos->getZ(),
			$def["volume"],
			$def["pitch"]
		);

		foreach($players as $player){
			if(!($player instanceof Player)){
				continue;
			}
			$player->getNetworkSession()->sendDataPacket($packet);
		}
	}
}
