<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use customloader\manager\CustomEntityManager;
use pocketmine\entity\Entity;
use pocketmine\world\Location;

/**
 * Spawns a registered custom entity near the source entity.
 *
 * YAML fields:
 *   action: spawn_entity
 *   entity: "mypack:my_mob"
 *   offset: [0, 1, 0]        # optional XYZ offset from source position (default: [0,0,0])
 *   count:  1                 # how many to spawn (default: 1)
 */
final class SpawnEntityAction implements EventAction{

    /** @param float[] $offset */
    public function __construct(
        private readonly string $entityNamespace,
        private readonly array  $offset,
        private readonly int    $count,
    ){}

    public function getActionType() : string{
        return "spawn_entity";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $pos   = $source->getPosition();
        $world = $pos->getWorld();

        $ox = (float) ($this->offset[0] ?? 0.0);
        $oy = (float) ($this->offset[1] ?? 0.0);
        $oz = (float) ($this->offset[2] ?? 0.0);

        $location = Location::fromObject(
            $pos->add($ox, $oy, $oz),
            $world,
        );

        $manager = CustomEntityManager::getInstance();
        $spawnCount = max(1, $this->count);

        for($i = 0; $i < $spawnCount; $i++){
            $manager->spawnEntity($location, $this->entityNamespace);
        }
    }

    public static function fromData(array $data) : static{
        $rawOffset = $data["offset"] ?? [0, 0, 0];
        $offset = [
            (float) ($rawOffset[0] ?? 0.0),
            (float) ($rawOffset[1] ?? 0.0),
            (float) ($rawOffset[2] ?? 0.0),
        ];

        return new static(
            entityNamespace: (string) ($data["entity"] ?? ""),
            offset:          $offset,
            count:           (int) ($data["count"] ?? 1),
        );
    }
}
