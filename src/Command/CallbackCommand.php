<?php

/*
 * This file is part of KoolKode Process.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Process\Command;

use KoolKode\Process\EngineInterface;

/**
 * Wrapper for a callable to be used as command executed by a process engine.
 * 
 * @author Martin Schröder
 */
class CallbackCommand implements CommandInterface
{
	protected $callback;
	
	protected $priority;
	
	/**
	 * Create a command from the given callback and priority.
	 * 
	 * @param callable $callback
	 * @param integer $priority
	 */
	public function __construct(callable $callback, $priority = self::PRIORITY_DEFAULT)
	{
		$this->callback = $callback;
		$this->priority = (int)$priority;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function isSerializable()
	{
		return false;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getPriority()
	{
		return $this->priority;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(EngineInterface $engine)
	{
		call_user_func($this->callback, $engine);
	}
}
