<?php

declare(strict_types=1);

namespace customloader\item;

use pocketmine\item\Food;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

class CustomFoodItem extends Food implements CustomItemInterface{
	use CustomItemTrait;

	public function getMaxStackSize() : int{
		return $this->getProperties()->getMaxStackSize();
	}

	public function getFoodRestore() : int{
		return $this->getProperties()->getNutrition();
	}

	public function requiresHunger() : bool{
		return !$this->getProperties()->getCanAlwaysEat();
	}

	public function getSaturationRestore() : float{
		return $this->getProperties()->getSaturation();
	}

	public function getResidue() : Item{
		// Custom food items leave no residue item by default.
		// Override this class to customize (e.g., empty bowl after stew).
		return VanillaItems::AIR();
	}
}
