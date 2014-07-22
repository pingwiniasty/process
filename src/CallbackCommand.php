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
	
	protected $priority;
	
	public function __construct(callable $callback, $priority = self::PRIORITY_DEFAULT)
	{
		$this->callback = $callback;
		$this->priority = (int)$priority;
	}
	
	public function getPriority()
	{
		return $this->priority;
	}
	
	public function execute(EngineInterface $engine)
	{
		call_user_func($this->callback, $engine);
	}
}
