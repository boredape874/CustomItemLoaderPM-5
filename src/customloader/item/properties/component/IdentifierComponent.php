<?php

declare(strict_types=1);

namespace customloader\item\properties\component;

use pocketmine\nbt\tag\CompoundTag;

final class IdentifierComponent extends Component{

	public const TAG_IDENTIFIER = "minecraft:identifier";

	public function __construct(private readonly int $runtimeId){}

	public function getName() : string{
		return "identifier";
	}

	public function processComponent(CompoundTag $rootNBT) : void{
		$rootNBT->setShort(self::TAG_IDENTIFIER, $this->runtimeId);
	}
}
