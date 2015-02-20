<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Behavior;

use KoolKode\Process\Execution;

/**
 * Synchronizes concurrent executions, requires an inactive concurrent execution to
 * be present at every incoming transition.
 * 
 * @author Martin Schröder
 */
class SyncBehavior implements BehaviorInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function execute(Execution $execution)
	{
		$execution->setActive(false);

		$numberExecutions = count($execution->getProcessModel()->findIncomingTransitions($execution->getNode()->getId()));
		
		// Collect recycled executions, initialize with current execution:
		$recycle = [$execution->getTransition()->getId() => $execution];
		
		foreach($execution->findInactiveConcurrentExecutions($execution->getNode()) as $concurrent)
		{
			// Collect at most 1 execution per incoming transition.
			$transId = $concurrent->getTransition()->getId();
				
			if(empty($recycle[$transId]))
			{
				$recycle[$transId] = $concurrent;
			}
		}
		
		if(count($recycle) !== $numberExecutions)
		{
			return;
		}
		
		return $execution->takeAll(NULL, $recycle);
	}
}
