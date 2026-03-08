<?php

declare(strict_types=1);

namespace customloader\entity\ai\goal;

use function ksort;

final class GoalSelector{

	/** @var array<int, Goal[]> priority => goals */
	private array $goals = [];
	/** @var array<int, Goal> priority => currently active goal */
	private array $runningGoals = [];

	public function addGoal(int $priority, Goal $goal) : void{
		$this->goals[$priority][] = $goal;
	}

	public function tick() : void{
		// Stop goals that can no longer run
		foreach($this->runningGoals as $priority => $goal){
			if(!$goal->canContinueToUse()){
				$goal->stop();
				unset($this->runningGoals[$priority]);
			}
		}

		// Try to start new goals (lower priority number = higher priority)
		ksort($this->goals);
		foreach($this->goals as $priority => $goalList){
			if(isset($this->runningGoals[$priority])){
				continue; // Already running a goal at this priority level
			}
			foreach($goalList as $goal){
				if($goal->canUse()){
					// Interrupt lower-priority (higher-number) interruptable running goals
					foreach($this->runningGoals as $runPriority => $runGoal){
						if($runPriority > $priority && $runGoal->isInterruptable()){
							$runGoal->stop();
							unset($this->runningGoals[$runPriority]);
						}
					}
					$goal->start();
					$this->runningGoals[$priority] = $goal;
					break;
				}
			}
		}

		// Tick all running goals
		foreach($this->runningGoals as $goal){
			$goal->tick();
		}
	}

	public function stopAll() : void{
		foreach($this->runningGoals as $goal){
			$goal->stop();
		}
		$this->runningGoals = [];
	}
}
