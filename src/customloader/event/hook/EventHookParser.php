<?php

declare(strict_types=1);

namespace customloader\event\hook;

use customloader\event\hook\action\DamageAction;
use customloader\event\hook\action\GiveEffectAction;
use customloader\event\hook\action\GiveItemAction;
use customloader\event\hook\action\GiveXpAction;
use customloader\event\hook\action\PlayParticleAction;
use customloader\event\hook\action\PlaySoundAction;
use customloader\event\hook\action\RunCommandAction;
use customloader\event\hook\action\SendMessageAction;
use customloader\event\hook\action\SendTitleAction;
use customloader\event\hook\action\SetHealthAction;
use customloader\event\hook\action\SetOnFireAction;
use customloader\event\hook\action\SpawnEntityAction;
use customloader\event\hook\action\TeleportAction;
use pocketmine\entity\Entity;

/**
 * Static utility for parsing YAML action lists into EventAction arrays
 * and executing them against a source/target pair.
 *
 * Supported action types:
 *   give_effect, play_sound, spawn_entity, run_command,
 *   damage, give_item, play_particle,
 *   send_message, send_title, teleport,
 *   set_on_fire, give_xp, set_health
 */
final class EventHookParser{

    private function __construct(){
        // Static utility class — not instantiable.
    }

    /**
     * Map of action type string => callable(array): EventAction.
     *
     * @var array<string, class-string<EventAction>>
     */
    private const ACTION_MAP = [
        "give_effect"   => GiveEffectAction::class,
        "play_sound"    => PlaySoundAction::class,
        "spawn_entity"  => SpawnEntityAction::class,
        "run_command"   => RunCommandAction::class,
        "damage"        => DamageAction::class,
        "give_item"     => GiveItemAction::class,
        "play_particle" => PlayParticleAction::class,
        "send_message"  => SendMessageAction::class,
        "send_title"    => SendTitleAction::class,
        "teleport"      => TeleportAction::class,
        "set_on_fire"   => SetOnFireAction::class,
        "give_xp"       => GiveXpAction::class,
        "set_health"    => SetHealthAction::class,
    ];

    /**
     * Parse a raw YAML action list into an array of EventAction instances.
     * Entries with an unknown or missing "action" key are silently skipped.
     *
     * @param array<int, array<string, mixed>> $actionList
     * @return EventAction[]
     */
    public static function parse(array $actionList) : array{
        $actions = [];

        foreach($actionList as $entry){
            if(!is_array($entry)){
                continue;
            }

            $type = (string) ($entry["action"] ?? "");
            $class = self::ACTION_MAP[$type] ?? null;

            if($class === null){
                continue;
            }

            try{
                /** @var EventAction $action */
                $action = $class::fromData($entry);
                $actions[] = $action;
            }catch(\Throwable){
                // Skip malformed action entries rather than crashing the plugin.
            }
        }

        return $actions;
    }

    /**
     * Execute a list of pre-parsed actions against the given source and optional target.
     *
     * @param EventAction[] $actions
     */
    public static function execute(array $actions, Entity $source, ?Entity $target = null) : void{
        foreach($actions as $action){
            try{
                $action->execute($source, $target);
            }catch(\Throwable){
                // Isolate action failures so one bad action does not abort the rest.
            }
        }
    }
}
