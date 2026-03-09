<?php

declare(strict_types=1);

namespace customloader\event\hook\action;

use customloader\event\hook\EventAction;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Sends a title/subtitle to the source player.
 *
 * Config:
 *   - action: send_title
 *     title: "Level Up!"
 *     subtitle: "You are now stronger"   # optional
 *     fade_in: 10                        # ticks, default 10
 *     stay: 70                           # ticks, default 70
 *     fade_out: 20                       # ticks, default 20
 */
final class SendTitleAction implements EventAction{

	private string $title;
	private string $subtitle;
	private int    $fadeIn;
	private int    $stay;
	private int    $fadeOut;

	private function __construct(array $data){
		$this->title    = (string) ($data["title"]    ?? "");
		$this->subtitle = (string) ($data["subtitle"] ?? "");
		$this->fadeIn   = max(0, (int) ($data["fade_in"]  ?? 10));
		$this->stay     = max(1, (int) ($data["stay"]     ?? 70));
		$this->fadeOut  = max(0, (int) ($data["fade_out"] ?? 20));
	}

	public static function fromData(array $data) : static{
		return new self($data);
	}

	public function getActionType() : string{
		return "send_title";
	}

	public function execute(Entity $source, ?Entity $target = null) : void{
		if(!($source instanceof Player)){
			return;
		}
		$source->sendTitle(
			$this->title,
			$this->subtitle,
			$this->fadeIn,
			$this->stay,
			$this->fadeOut
		);
	}
}
