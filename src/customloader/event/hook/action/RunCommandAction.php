<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\Server;

/**
 * Runs a server command, either as the console (OP) or as the source player.
 *
 * YAML fields:
 *   action:  run_command
 *   command: "say Hello!"
 *   as_op:   true            # true = run via ConsoleCommandSender (default: true)
 *                            # false = run as the source player (if they are a Player)
 */
final class RunCommandAction implements EventAction{

    public function __construct(
        private readonly string $command,
        private readonly bool   $asOp,
    ){}

    public function getActionType() : string{
        return "run_command";
    }

    public function execute(Entity $source, ?Entity $target) : void{
        $server     = Server::getInstance();
        $commandMap = $server->getCommandMap();

        if($this->asOp || !($source instanceof Player)){
            // Run as console (has root-level permissions).
            $sender = new ConsoleCommandSender($server, $server->getLanguage());
            $commandMap->dispatch($sender, $this->command);
        }else{
            // Run as the player — they must have the required permissions.
            $commandMap->dispatch($source, $this->command);
        }
    }

    public static function fromData(array $data) : static{
        return new static(
            command: (string) ($data["command"] ?? ""),
            asOp:    (bool)   ($data["as_op"]   ?? true),
        );
    }
}
