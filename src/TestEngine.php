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

use KoolKode\Util\UUID;
use KoolKode\Process\Event\StartProcessEvent;

/**
 * Provides a very basic in-memory process engine that can be used for unit testing etc.
 * 
 * @author Martin Schröder
 */
class TestEngine extends AbstractEngine
{
	protected $executions = [];
	
	public function registerExecution(Execution $execution)
	{
		$this->executions[(string)$execution->getId()] = $execution;
	}
	
	public function startProcess(ProcessDefinition $definition, array $variables = [], callable $factory = NULL)
	{	
		$initial = $definition->findInitialNodes();
		
		if(count($initial) != 1)
		{
			throw new \RuntimeException(sprintf('Process "%s" does not declare exactly 1 start node', $definition->getTitle()));
		}
		
		$startNode = array_shift($initial);
		
		if($factory === NULL)
		{
			$process = new Execution(UUID::createRandom(), $this, $definition);
		}
		else
		{
			$process = $factory(UUID::createRandom(), $this, $definition);
		}
		
		foreach($variables as $k => $v)
		{
			$process->setVariable($k, $v);
		}
		
		$this->registerExecution($process);
		$this->notify(new StartProcessEvent($startNode, $process));
		
		$process->execute($startNode);
		
		return $process;
	}
	
	public function countWaiting(Execution $execution, Node $node = NULL)
	{
		return count($this->findWaitingExecutions($execution, $node));
	}
	
	public function findWaitingExecutions(Execution $execution, Node $node = NULL)
	{
		return array_values(array_filter($this->collectExecutions($execution, $node), function(Execution $ex) {
			return $ex->isWaiting();
		}));
	}
	
	public function countConcurrent(Execution $execution, Node $node = NULL)
	{
		return count($this->findConcurrentExecutions($execution, $node));
	}
	
	public function findConcurrentExecutions(Execution $execution, Node $node = NULL)
	{
		return array_values(array_filter($this->collectExecutions($execution, $node), function(Execution $ex) {
			return $ex->isConcurrent();
		}));
	}
	
	/**
	 * Signal the given execution if it is in a wait state.
	 * 
	 * @param Execution $execution
	 * @param string $signal
	 */
	public function signal(Execution $execution, $signal = NULL, array $variables = [])
	{
		if($execution->isWaiting())
		{
			$execution->signal($signal, $variables);
		}
	}
	
	/**
	 * Signal all waiting executions.
	 * 
	 * @param Execution $execution Concurrent root execution.
	 * @param Node $node Constrain signalign to executions within this node.
	 * @param string $signal The signal to send.
	 */
	public function signalAll(Execution $execution, Node $node = NULL, $signal = NULL, array $variables = [])
	{
		foreach($this->collectExecutions($execution, $node) as $exec)
		{
			if($exec->isWaiting())
			{
				$exec->signal($signal, $variables);
			}
		}
	}
	
	protected function collectExecutions(Execution $execution, Node $node = NULL)
	{
		$executions = [$execution];
	
		foreach($execution->findChildExecutions($node) as $child)
		{
			foreach($this->collectExecutions($child, $node) as $exec)
			{
				$executions[] = $exec;
			}
		}
	
		return $executions;
	}
}
