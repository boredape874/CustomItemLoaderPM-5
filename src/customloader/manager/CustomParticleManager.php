<?php

declare(strict_types=1);

namespace customloader\manager;

use pocketmine\utils\SingletonTrait;
use function array_key_exists;

/**
 * Registry for custom particle effect definitions loaded from config.yml.
 *
 * Config format:
 * ```yaml
 * particles:
 *   ruby_burst:
 *     texture: "particles/ruby_burst"   # texture path inside the resource pack
 *     count: 10                          # number of particles per emission (optional)
 * ```
 *
 * Particle effects are purely client-side in Bedrock Edition.
 * The definitions stored here are consumed by:
 *   1. ResourcePackBuilder — writes particles/ JSON files into the RP.
 *   2. Any game logic that wants to trigger a particle (send SpawnParticleEffectPacket
 *      manually, keyed by the particle namespace registered in the RP).
 *
 * The `namespace` used in the network packet is built from the particle name as
 * "<pack_name>:<particle_name>", matching the RP particle identifier.
 * ResourcePackBuilder is responsible for that mapping; this manager only stores
 * the raw config data.
 *
 * Usage:
 * ```php
 * $def = CustomParticleManager::getInstance()->get("ruby_burst");
 * if ($def !== null) {
 *     // $def["texture"], $def["count"]
 * }
 * ```
 */
final class CustomParticleManager{
	use SingletonTrait;

	/**
	 * @var array<string, array{texture: string, count: int}>
	 *             Particle name => definition
	 */
	private array $particles = [];

	public function __construct(){}

	/**
	 * Bulk-register particles from the config "particles" section.
	 *
	 * @param array<string, mixed> $data Raw config array (particles section).
	 */
	public function registerDefaultParticles(array $data) : void{
		foreach($data as $name => $def){
			$name = (string) $name;
			$def  = (array)  $def;

			if(!isset($def["texture"])){
				// Skip entries without a texture path; warn at load time via plugin logger
				continue;
			}

			$this->register($name, $def);
		}
	}

	/**
	 * Register a single particle definition.
	 *
	 * @param array<string, mixed> $def Must contain "texture". "count" is optional (default 1).
	 */
	public function register(string $name, array $def) : void{
		$this->particles[$name] = [
			"texture" => (string) $def["texture"],
			"count"   => max(1, (int) ($def["count"] ?? 1)),
		];
	}

	/**
	 * Returns the definition for the given particle name, or null if not registered.
	 *
	 * @return array{texture: string, count: int}|null
	 */
	public function get(string $name) : ?array{
		return $this->particles[$name] ?? null;
	}

	public function has(string $name) : bool{
		return array_key_exists($name, $this->particles);
	}

	/**
	 * Returns all registered particle definitions.
	 * Used by ResourcePackBuilder when generating particle JSON files.
	 *
	 * @return array<string, array{texture: string, count: int}>
	 */
	public function getAll() : array{
		return $this->particles;
	}
}
