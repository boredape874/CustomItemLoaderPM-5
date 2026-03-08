<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use function method_exists;
use function strtoupper;

/**
 * Applies a potion effect to the source or target entity.
 *
 * YAML fields:
 *   action:    give_effect
 *   effect:    speed          # VanillaEffects method name (case-insensitive)
 *   duration:  200            # ticks (default: 200)
 *   amplifier: 0              # amplifier level (default: 0)
 *   target:    self           # "self" (default) or "target"
 */
final class GiveEffectAction implements EventAction{

    public function __construct(
        private readonly string $effectName,
        private readonly int    $duration,
        private readonly int    $amplifier,
        private readonly string $target,
    ){}

    public function getActionType() : string{
        return "give_effect";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $recipient = $this->resolveTarget($source, $target);
        if(!($recipient instanceof Living)){
            return;
        }

        $effectType = $this->resolveEffect($this->effectName);
        if($effectType === null){
            return;
        }

        $recipient->addEffect(new EffectInstance($effectType, $this->duration, $this->amplifier));
    }

    private function resolveTarget(Entity $source, ?Entity $target) : ?Entity{
        if($this->target === "target"){
            return $target;
        }
        return $source;
    }

    private function resolveEffect(string $name) : ?\pocketmine\entity\effect\Effect{
        $methodName = strtoupper($name);
        if(!method_exists(VanillaEffects::class, $methodName)){
            return null;
        }
        try{
            /** @var \pocketmine\entity\effect\Effect $effect */
            $effect = VanillaEffects::$methodName();
            return $effect;
        }catch(\Throwable){
            return null;
        }
    }

    public static function fromData(array $data) : static{
        return new static(
            effectName: (string) ($data["effect"]   ?? "speed"),
            duration:   (int)   ($data["duration"]  ?? 200),
            amplifier:  (int)   ($data["amplifier"] ?? 0),
            target:     (string) ($data["target"]   ?? "self"),
        );
    }
}
