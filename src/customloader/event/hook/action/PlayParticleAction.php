<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\Position;
use function array_filter;
use function array_values;

/**
 * Sends a particle effect to all players within a given radius.
 *
 * YAML fields:
 *   action:   play_particle
 *   particle: "minecraft:critical_hit_emitter"
 *   offset:   [0, 1, 0]      # optional XYZ offset from source (default: [0,0,0])
 *   radius:   16              # broadcast radius in blocks (default: 16)
 */
final class PlayParticleAction implements EventAction{

    /** @param float[] $offset */
    public function __construct(
        private readonly string $particleName,
        private readonly array  $offset,
        private readonly float  $radius,
    ){}

    public function getActionType() : string{
        return "play_particle";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $pos   = $source->getPosition();
        $world = $pos->getWorld();

        $ox = (float) ($this->offset[0] ?? 0.0);
        $oy = (float) ($this->offset[1] ?? 0.0);
        $oz = (float) ($this->offset[2] ?? 0.0);

        $spawnPos = new Vector3(
            $pos->getX() + $ox,
            $pos->getY() + $oy,
            $pos->getZ() + $oz,
        );

        // Resolve dimension ID from world dimension constant.
        $dimensionId = $world->getDimension() === \pocketmine\world\World::DIMENSION_NETHER
            ? DimensionIds::NETHER
            : ($world->getDimension() === \pocketmine\world\World::DIMENSION_THE_END
                ? DimensionIds::THE_END
                : DimensionIds::OVERWORLD);

        $packet = SpawnParticleEffectPacket::create(
            dimensionId:          $dimensionId,
            actorUniqueId:        -1,
            position:             $spawnPos,
            particleName:         $this->particleName,
            molangVariablesJson:  null,
        );

        $nearbyPlayers = $this->getPlayersInRadius($pos, $this->radius);
        if($nearbyPlayers === []){
            return;
        }

        NetworkBroadcastUtils::broadcastPackets($nearbyPlayers, [$packet]);
    }

    /**
     * @return \pocketmine\player\Player[]
     */
    private function getPlayersInRadius(Position $pos, float $radius) : array{
        $world    = $pos->getWorld();
        $radiusSq = $radius * $radius;

        $players = array_filter(
            $world->getPlayers(),
            static function(\pocketmine\player\Player $player) use ($pos, $radiusSq) : bool{
                return $player->getPosition()->distanceSquared($pos) <= $radiusSq;
            }
        );

        return array_values($players);
    }

    public static function fromData(array $data) : static{
        $rawOffset = $data["offset"] ?? [0, 0, 0];
        $offset = [
            (float) ($rawOffset[0] ?? 0.0),
            (float) ($rawOffset[1] ?? 0.0),
            (float) ($rawOffset[2] ?? 0.0),
        ];

        return new static(
            particleName: (string) ($data["particle"] ?? "minecraft:generic_emitter"),
            offset:       $offset,
            radius:       (float)  ($data["radius"]   ?? 16.0),
        );
    }
}
