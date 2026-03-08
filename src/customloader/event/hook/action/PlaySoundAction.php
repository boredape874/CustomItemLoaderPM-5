<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\world\Position;
use function array_filter;
use function array_values;

/**
 * Broadcasts a sound to all players within a given radius of the source entity.
 *
 * YAML fields:
 *   action: play_sound
 *   sound:  "random.orb"
 *   volume: 1.0              # default: 1.0
 *   pitch:  1.0              # default: 1.0
 *   radius: 16               # broadcast radius in blocks (default: 16)
 */
final class PlaySoundAction implements EventAction{

    public function __construct(
        private readonly string $sound,
        private readonly float  $volume,
        private readonly float  $pitch,
        private readonly float  $radius,
    ){}

    public function getActionType() : string{
        return "play_sound";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $pos   = $source->getPosition();
        $world = $pos->getWorld();

        $packet = PlaySoundPacket::create(
            $this->sound,
            $pos->getX(),
            $pos->getY(),
            $pos->getZ(),
            $this->volume,
            $this->pitch,
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
        $world = $pos->getWorld();
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
        return new static(
            sound:  (string) ($data["sound"]  ?? "random.orb"),
            volume: (float)  ($data["volume"] ?? 1.0),
            pitch:  (float)  ($data["pitch"]  ?? 1.0),
            radius: (float)  ($data["radius"] ?? 16.0),
        );
    }
}
