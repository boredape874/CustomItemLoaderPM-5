<?php

declare(strict_types=1);

namespace customloader\block;

use customloader\block\properties\CustomBlockProperties;

trait CustomBlockTrait{

	private CustomBlockProperties $properties;

	public function getProperties() : CustomBlockProperties{
		return $this->properties;
	}
}
