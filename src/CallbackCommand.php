<?php

/*
 * This file is part of KoolKode Process.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Process;

/**
 * Wrapper for a callable to be used as command executed by a process engine.
 * 
 * @author Martin Schröder
 */
class CallbackCommand implements CommandInterface
{
	protected $callback;
	
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}
	
	public function execute(EngineInterface $engine)
	{
		call_user_func($this->callback, $engine);
	}
}
