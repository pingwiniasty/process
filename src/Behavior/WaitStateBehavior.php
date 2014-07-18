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
use KoolKode\Process\SignalableActivityInterface;

class WaitStateBehavior implements SignalableActivityInterface
{
	public function execute(Execution $execution)
	{
		$execution->waitForSignal();
	}
	
	public function signal(Execution $execution, $signal, array $variables = [])
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
