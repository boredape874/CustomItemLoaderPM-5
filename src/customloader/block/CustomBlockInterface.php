<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;

/**
 * Shared interface implemented by all custom block types:
 *   - CustomBlock  (cube)
 *   - CustomSlabBlock
 *   - CustomStairBlock
 *
 * Allows CustomBlockManager to store all shapes in one typed array
 * while still calling getProperties() regardless of which PM5 base class
 * each shape requires.
 */
interface CustomBlockInterface{
	public function getProperties() : CustomBlockProperties;
}
