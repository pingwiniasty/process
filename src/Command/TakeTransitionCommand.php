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
use KoolKode\Process\Event\LeaveNodeEvent;
use KoolKode\Process\Event\TakeTransitionEvent;
use KoolKode\Process\Execution;
use KoolKode\Process\Transition;
use KoolKode\Util\UUID;

/**
 * Have an execution transition from one node into the next new node.
 * 
 * @author Martin Schröder
 */
class TakeTransitionCommand extends AbstractCommand
{
	/**
	 * ID of the execution.
	 * 
	 * @var UUID
	 */
	protected $executionId;
	
	/**
	 * ID of the transition to be taken out of the current node.
	 * 
	 * @var string
	 */
	protected $transitionId;
	
	/**
	 * Have the execution transition into the next node.
	 * 
	 * @param Execution $execution
	 * @param Transition $transition
	 */
	public function __construct(Execution $execution, Transition $transition = NULL)
	{
		$this->executionId = $execution->getId();
		$this->transitionId = ($transition === NULL) ? NULL : (string)$transition->getId();
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
	public function execute(EngineInterface $engine)
	{
		$execution = $engine->findExecution($this->executionId);
		
		if($execution->isConcurrent())
		{
			if(1 === count($execution->findConcurrentExecutions()))
			{
				$parent = $execution->getParentExecution();
				
				foreach($execution->findChildExecutions() as $child)
				{
					$parent->registerChildExecution($child);
				}
				
				$parent->setNode($execution->getNode());
				$parent->setTransition($execution->getTransition());
				$parent->setActive(true);
				$parent->markModified(true);
				
				$engine->debug('Merged concurrent {execution} into {root}', [
					'execution' => (string)$execution,
					'root' => (string)$parent
				]);

				$execution->terminate();
				
				return $parent->take($this->transitionId);
			}
		}
		
		$trans = $this->findTransition($execution);

		if(!$trans->isEnabled($execution))
		{
			$execution->terminate();
		
			if($execution->isConcurrent() && 0 == count($execution->findConcurrentExecutions()))
			{
				$parent = $execution->getParentExecution();
				
				$parent->setActive(true);
				$parent->terminate();
			}
		
			return;
		}
		
		$node = $execution->getNode();
			
		$engine->debug('{execution} leaves {node}', [
			'execution' => (string)$execution,
			'node' => (string)$node
		]);
		$engine->notify(new LeaveNodeEvent($node, $execution));
			
		$engine->debug('{execution} taking {transition}', [
			'execution' => (string)$execution,
			'transition' => (string)$trans
		]);
		$engine->notify(new TakeTransitionEvent($trans, $execution));
		
		$execution->setTimestamp(microtime(true));
		$execution->setTransition($trans);
		
		$execution->execute($execution->getProcessModel()->findNode($trans->getTo()));
	}
	
	/**
	 * Find the outgoing transition to be taken by the given execution.
	 * 
	 * @param Execution $execution
	 * @throws \RuntimeException
	 * @return Transition
	 */
	protected function findTransition(Execution $execution)
	{
		$out = (array)$execution->getProcessModel()->findOutgoingTransitions($execution->getNode()->getId());
		$trans = NULL;
		
		if($this->transitionId === NULL)
		{
			if(count($out) != 1)
			{
				throw new \RuntimeException(sprintf('No single outgoing transition found at node "%s"', $execution->getNode()->getId()));
			}
		
			return array_pop($out);
		}

		foreach($out as $tmp)
		{
			if($tmp->getId() === $this->transitionId)
			{
				$trans = $tmp;
				
				break;
			}
		}
	
		if($trans === NULL)
		{
			throw new \RuntimeException(sprintf('Transition "%s" not connected to node "%s"', $this->transitionId, $execution->getNode()->getId()));
		}
		
		return $trans;
	}
}
