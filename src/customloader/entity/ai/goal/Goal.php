<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

interface Goal{

	/**
	 * Whether this goal can be started right now.
	 */
	public function canUse() : bool;

	/**
	 * Whether this goal can continue running once started.
	 * Defaults to canUse() unless overridden.
	 */
	public function canContinueToUse() : bool;

	/**
	 * Called when this goal is activated.
	 */
	public function start() : void;

	/**
	 * Called every tick while this goal is active.
	 */
	public function tick() : void;

	/**
	 * Called when this goal is deactivated.
	 */
	public function stop() : void;

	/**
	 * Whether a higher-priority goal can interrupt this goal.
	 */
	public function isInterruptable() : bool;
}
