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

use KoolKode\Process\BehaviorInterface;
use KoolKode\Process\Execution;

/**
 * Uses a generic PHP callback as behavior of a node.
 * 
 * @author Martin Schröder
 */
class CallbackBehavior implements BehaviorInterface, \Serializable
{
	protected $callback;
	protected $takeAll;
	
	public function __construct(callable $callback, $takeAll = true)
	{
		$this->callback = $callback;
		$this->takeAll = $takeAll ? true : false;
	}
	
	public function serialize()
	{
		throw new \RuntimeException('Callback behaviors are for testing only and cannot be serialized');
	}
	
	public function unserialize($serialized) { }
	
	public function isTakeAll()
	{
		return $this->takeAll;
	}
	
	public function execute(Execution $execution)
	{
		call_user_func($this->callback, $execution);
		
		if($this->takeAll)
		{
			if($execution->isWaiting() || !$execution->isActive() || $execution->isTerminated())
			{
				return;
			}
			
			$execution->takeAll();
		}
	}
}
