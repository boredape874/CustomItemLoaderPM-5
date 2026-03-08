<?php

declare(strict_types=1);

namespace customloader\event\hook;

use pocketmine\entity\Entity;

interface EventAction{

    /**
     * Execute the action.
     *
     * @param Entity      $source The entity that triggered the event (the "self").
     * @param Entity|null $target The target entity (e.g. victim on on_attack; null for on_use).
     */
    public function execute(Entity $source, ?Entity $target) : void;

    /**
     * Parse from YAML data array and return a new instance.
     *
     * Note: Not declared abstract static intentionally — PHP does not enforce
     * abstract static in interfaces usefully. Implementors must define this.
     *
     * @param array<string, mixed> $data
     */
    public static function fromData(array $data) : static;

    /**
     * Returns the action type identifier string (e.g. "give_effect").
     */
    public function getActionType() : string;
}
