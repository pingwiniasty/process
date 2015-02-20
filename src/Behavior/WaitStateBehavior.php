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
 * Puts any arriving execution into a wait state, signaling continues an execution.
 * 
 * @author Martin Schröder
 */
class WaitStateBehavior implements SignalableBehaviorInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function execute(Execution $execution)
	{
		$execution->waitForSignal();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function signal(Execution $execution, $signal, array $variables = [], array $delegation = [])
	{
		foreach($variables as $k => $v)
		{
			$execution->setVariable($k, $v);
		}
		
		if($signal === NULL)
		{
			return $execution->takeAll(NULL, [$execution]);
		}
		
		return $execution->take($signal);
	}
}
