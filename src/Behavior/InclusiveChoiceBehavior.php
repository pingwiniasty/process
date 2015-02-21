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
use KoolKode\Process\Node;

/**
 * Turns a node into an inclusive fork / join with an optional default transition.
 *
 * @author Martin Schröder
 */
class InclusiveChoiceBehavior implements BehaviorInterface
{
	protected $defaultTransition;
	
	public function __construct($defaultTransition = NULL)
	{
		$this->defaultTransition = ($defaultTransition === NULL) ? NULL : (string)$defaultTransition;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(Execution $execution)
	{
		if($this->hasConcurrentActiveExecution($execution))
		{
			return $execution->setActive(false);
		}
		
		$take = [];
		
		foreach($execution->getProcessModel()->findOutgoingTransitions($execution->getNode()->getId()) as $trans)
		{
			if($trans->getId() === $this->defaultTransition)
			{
				continue;
			}
			
			if($trans->isEnabled($execution))
			{
				$take[] = $trans;
			}
		}
		
		// Collect recycled executions, initialize with current execution:
		$recycle = [$execution->getTransition()->getId() => $execution];
		
		foreach($execution->findInactiveConcurrentExecutions($execution->getNode()) as $concurrent)
		{
			// Collect at most 1 execution per incoming transition.
			$transId = $concurrent->getTransition()->getId();
			
			if(empty($recycle[$transId]) || $concurrent->getTimestamp() < $recycle[$transId]->getTimestamp())
			{
				$recycle[$transId] = $concurrent;
			}
		}
		
		if(!empty($take))
		{
			return $execution->takeAll($take, $recycle);
		}
		
		if($this->defaultTransition !== NULL)
		{
			return $execution->takeAll([$this->defaultTransition], $recycle);
		}
		
		$message = sprintf(
			'Execution %s about to get stuck in inclusive choice within node "%s"',
			$execution->getId(),
			$execution->getNode()->getId()
		);
		
		throw new StuckException($message);
	}
	
	protected function hasConcurrentActiveExecution(Execution $execution)
	{
		if(!$execution->isConcurrent())
		{
			return false;
		}
		
		foreach($execution->findConcurrentExecutions() as $concurrent)
		{
			if($concurrent === $execution)
			{
				continue;
			}
			
			if(!$concurrent->isActive() || $concurrent->getNode() === $execution->getNode())
			{
				continue;
			}
			
			if($this->isReachable($concurrent->getNode(), $execution->getNode(), $execution, new \SplObjectStorage()))
			{
				return true;
			}
		}
	}
	
	protected function isReachable(Node $source, Node $target, Execution $execution, \SplObjectStorage $visited)
	{
		if($source === $target)
		{
			return true;
		}
		
		$model = $execution->getProcessModel();
		$out = $model->findOutgoingTransitions($source->getId());
		$visited->attach($source);
		
		foreach($out as $transition)
		{
			$tmp = $model->findNode($transition->getTo());
			
			if(!$visited->contains($tmp) && $this->isReachable($tmp, $target, $execution, $visited))
			{
				return true;
			}
		}
		
		return false;
	}
}
