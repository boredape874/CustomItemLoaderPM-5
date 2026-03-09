<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Sends a chat message, tip, or popup to the source player.
 *
 * Config:
 *   - action: send_message
 *     message: "Hello, {player}!"   # {player} = player name, {target} = target name
 *     type: chat                    # chat (default) | tip | popup
 */
final class SendMessageAction implements EventAction{

	private string $message;
	private string $type; // "chat" | "tip" | "popup"

	private function __construct(array $data){
		$this->message = (string) ($data["message"] ?? "");
		$this->type    = strtolower((string) ($data["type"] ?? "chat"));
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "send_message";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		if(!($source instanceof Player) || $this->message === ""){
			return;
		}

		$text = $this->replacePlaceholders($this->message, $source, $target);

		match($this->type){
			"tip"   => $source->sendTip($text),
			"popup" => $source->sendPopup($text),
			default => $source->sendMessage($text),
		};
	}

	private function replacePlaceholders(string $text, Player $player, ?Entity $target) : string{
		$text = str_replace("{player}", $player->getName(), $text);
		if($target instanceof Player){
			$text = str_replace("{target}", $target->getName(), $text);
		}elseif($target !== null){
			$text = str_replace("{target}", $target->getNameTag() ?: "entity", $text);
		}
		return $text;
	}
}
