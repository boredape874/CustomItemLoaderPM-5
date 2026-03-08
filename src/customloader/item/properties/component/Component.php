<?php

declare(strict_types=1);

namespace customloader\item\properties\component;

use pocketmine\nbt\tag\CompoundTag;

abstract class Component{

	public const TAG_COMPONENTS = "components";

	abstract public function getName() : string;

	public function buildComponent(CompoundTag $rootNBT) : void{
	}

	abstract public function processComponent(CompoundTag $rootNBT) : void;
}
