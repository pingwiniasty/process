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

use KoolKode\Process\ActivityInterface;
use KoolKode\Process\Execution;

/**
 * Synchronizes concurrent executions, requires an inactive concurrent execution to
 * be present at every incoming transition.
 * 
 * @author Martin Schr�der
 */
class SyncBehavior implements ActivityInterface
{
	public function execute(Execution $execution)
	{
		$execution->setActive(false);

		$numberExecutions = count($execution->getProcessDefinition()->findIncomingTransitions($execution->getNode()->getId()));
		
		if($numberExecutions == 1)
		{
			return $execution->takeAll();
		}
		
		$join = $execution->findInactiveConcurrentExecutions($execution->getNode());
		
		if($numberExecutions != count($join))
		{
			return;
		}
		
		return $execution->takeAll(NULL, $join);
	}
}
