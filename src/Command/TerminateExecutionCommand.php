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
use KoolKode\Process\Execution;

/**
 * Have the engine terminate a path of execution.
 * 
 * @author Martin Schröder
 */
class TerminateExecutionCommand extends AbstractCommand
{
	protected $executionId;
	
	protected $triggerExecution;
	
	public function __construct(Execution $execution, $triggerExecution = true)
	{
		$this->executionId = $execution->getId();
		$this->triggerExecution = $triggerExecution ? true : false;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function isSerializable()
	{
		return true;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getPriority()
	{
		return -1000000;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(EngineInterface $engine)
	{
		$engine->findExecution($this->executionId)->terminate($this->triggerExecution);
	}
}
