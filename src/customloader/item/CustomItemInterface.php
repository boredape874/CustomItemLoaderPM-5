<?php

declare(strict_types=1);

namespace customloader\item;

use customloader\item\properties\CustomItemProperties;

/**
 * Shared interface implemented by all custom item types:
 *   - CustomItem        (generic)
 *   - CustomFoodItem    (food)
 *   - CustomArmorItem   (armor)
 *   - CustomDurableItem (durable)
 *   - CustomToolItem    (tool)
 *
 * Allows EventListener and other systems to handle all custom item types
 * uniformly via instanceof without requiring a common base class.
 */
interface CustomItemInterface{
	public function getProperties() : CustomItemProperties;
}
