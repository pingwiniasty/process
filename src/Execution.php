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

use KoolKode\Process\Event\EnterNodeEvent;
use KoolKode\Process\Event\LeaveNodeEvent;
use KoolKode\Process\Event\TakeTransitionEvent;
use KoolKode\Util\UUID;

class Execution
{
	const STATE_NONE = 0;
	const STATE_WAIT = 1;
	const STATE_SCOPE = 2;
	const STATE_ACTIVE = 4;
	const STATE_CONCURRENT = 8;
	const STATE_TERMINATE = 16;
	
	protected $id;
	protected $state = self::STATE_ACTIVE;
	protected $timestamp = 0;
	protected $variables = [];
	
	protected $processDefinition;
	protected $transition;
	protected $node;
	
	protected $parentExecution;
	protected $childExecutions = [];
	
	protected $engine;
	
	public function __construct(UUID $id, EngineInterface $engine, ProcessDefinition $processDefinition, Execution $parentExecution)
	{
		$this->id = $id;
		$this->engine = $engine;
		$this->processDefinition = $processDefinition;
		$this->parentExecution = $parentExecution;
		
		$engine->debug('Created execution {0}', [(string)$this]);
	}
	
	public function __toString()
	{
		return sprintf('execution(%s)', $this->id);
	}

	public function getId()
	{
		return $this->id;
	}
	
	public function getEngine()
	{
		return $this->engine;
	}
	
	public function getExpressionContext()
	{
		return $this->engine->getExpressionContextFactory()->createContext($this);
	}
	
	public function isTerminated()
	{
		return 0 != ($this->state & self::STATE_TERMINATE);
	}
	
	public function terminate()
	{
		$this->state |= self::STATE_TERMINATE;
		
		$this->engine->debug('Terminate execution {0}', [(string)$this]);
		
		foreach($this->childExecutions as $execution)
		{
			if(!$execution->isTerminated())
			{
				$execution->terminate();
			}
		}
		
		if($this->parentExecution !== NULL)
		{
			$this->parentExecution->childExecutionTerminated($this);
		}
	}
	
	protected function childExecutionTerminated(Execution $execution)
	{
		foreach($this->childExecutions as $index => $exec)
		{
			if($exec === $execution)
			{
				unset($this->childExecutions[$index]);
		
				break;
			}
		}
	}
	
	public function getState()
	{
		return $this->state;
	}
	
	public function isActive()
	{
		return 0 != ($this->state & self::STATE_ACTIVE);
	}
	
	public function setActive($active)
	{
		$this->setState(self::STATE_ACTIVE, $active);
	}
	
	public function getTimestamp()
	{
		return $this->timestamp;
	}
	
	public function isConcurrent()
	{
		return 0 != ($this->state & self::STATE_CONCURRENT);
	}
	
	public function isWaiting()
	{
		return 0 != ($this->state & self::STATE_WAIT);
	}
	
	public function isScope()
	{
		return 0 != ($this->state & self::STATE_SCOPE);
	}
	
	public function getParentExecution()
	{
		return $this->parentExecution;
	}
	
	public function createExecution($concurrent = true)
	{
		$execution = new Execution(UUID::createRandom(), $this->engine, $this->processDefinition, $this);
		$execution->setNode($this->node);
		
		if($concurrent)
		{
			$execution->state |= self::STATE_CONCURRENT;
		}
		
		$this->engine->registerExecution($execution);
	
		return $this->childExecutions[] = $execution;
	}
	
	public function findChildExecutions(Node $node = NULL)
	{
		if($node === NULL)
		{
			return $this->childExecutions;
		}
		
		return array_filter($this->childExecutions, function(Execution $execution) use($node) {
			return $execution->getNode() === $node;
		});
	}
	
	public function findConcurrentExecutions(Node $node = NULL)
	{
		if($this->parentExecution === NULL)
		{
			return [];
		}
		
		return array_filter($this->parentExecution->findChildExecutions($node), function(Execution $execution) {
			return $execution->isConcurrent();
		});
	}
	
	public function findInactiveConcurrentExecutions(Node $node = NULL)
	{
		if($this->parentExecution === NULL)
		{
			return [];
		}
		
		return array_filter($this->parentExecution->findChildExecutions($node), function(Execution $execution) {
			return $execution->isConcurrent() && !$execution->isActive();
		});
	}
	
	public function findWaitingExecutions(Node $node = NULL)
	{
		return array_filter($this->findChildExecutions($node), function(Execution $execution) {
			return $execution->isActive() && $execution->isWaiting();
		});
	}
	
	public function getProcessInstance()
	{
		if($this->parentExecution === NULL)
		{
			return $this;
		}
		
		return $this->parentExecution->getProcessInstance();
	}
	
	public function hasVariable($name)
	{
		if($this->state & self::STATE_SCOPE)
		{
			return array_key_exists($name, $this->variables);
		}
		
		return $this->parentExecution->hasVariable($name);
	}
	
	public function getVariable($name)
	{
		if($this->state & self::STATE_SCOPE)
		{
			if(array_key_exists($name, $this->variables))
			{
				return $this->variables[$name];
			}
			
			if(func_num_args() > 1)
			{
				return func_get_arg(1);
			}
			
			throw new \OutOfBoundsException(sprintf('Variable "%s" not set in scope', $name));
		}
		
		if(func_num_args() > 1)
		{
			return $this->parentExecution->getVariable($name, func_get_arg(1));
		}
		else
		{
			return $this->parentExecution->getVariable($name);	
		}
	}
	
	public function setVariable($name, $value)
	{
		if($this->state & self::STATE_SCOPE)
		{
			if($value === NULL)
			{
				$this->removeVariable($name);
			}
			else
			{
				$this->variables[$name] = $value;
				
				$this->engine->debug('Set variable {0} in {1}', [(string)$name, (string)$this]);
			}
		}
		else
		{
			$this->parentExecution->setVariable($name, $value);
		}
	}
	
	public function removeVariable($name)
	{
		if($this->state & self::STATE_SCOPE)
		{
			unset($this->variables[$name]);
			
			$this->engine->debug('Removed variable {0} from {1}', [(string)$name, (string)$this]);
		}
		else
		{
			$this->parentExecution->removeVariable($name);
		}
	}
	
	public function getVariables()
	{
		return (array)$this->variables;
	}
	
	public function setVariables(array $variables)
	{
		$this->variables = $variables;
	}
	
	public function getNode()
	{
		return $this->node;
	}
	
	public function setNode(Node $node = NULL)
	{
		$this->node = $node;
	}
	
	public function hasTransition()
	{
		return $this->transition !== NULL;
	}
	
	public function getTransition()
	{
		return $this->transition;
	}
	
	public function getProcessDefinition()
	{
		return $this->processDefinition;
	}
	
	public function execute(Node $node)
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('%s is terminated', $this));
		}
		
		$this->engine->pushCommand(new CallbackCommand(function(EngineInterface $engine) use($node) {
			
			$this->timestamp = microtime(true);
			$this->node = $node;
			
			$this->engine->debug('{0} entering node {1}', [(string)$this, (string)$this->node]);
			$this->engine->notify(new EnterNodeEvent($this->node, $this));
			
			$activity = $this->node->getBehavior();
			
			if($activity instanceof ActivityInterface)
			{	
				$activity->execute($this);
			}
		}));
	}
	
	public function waitForSignal()
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Terminated %s cannot enter wait state', $this));
		}
		
		$this->timestamp = microtime(true);
		$this->state |= self::STATE_WAIT;
		
		$this->engine->debug('{0} enetered wait state', [(string)$this]);
	}
	
	public function signal($signal = NULL, array $variables = [])
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Cannot signal terminated %s', $this));
		}
		
		if(!$this->isWaiting())
		{
			throw new \RuntimeException(sprintf('%s is not in a wait state', $this));
		}
		
		$this->engine->pushCommand(new CallbackCommand(function() use($signal, $variables) {
			
			$this->timestamp = microtime(true);
			$this->setState(self::STATE_WAIT, false);
			
			$this->engine->debug('Signaling {0} to {1}', [$signal, (string)$this]);
			
			$activity = $this->node->getBehavior();
			
			if($activity instanceof SignalableActivityInterface)
			{
				$activity->signal($this, $signal, $variables);
			}
			else
			{
				$this->takeAll(NULL, [$this]);
			}
		}));
	}
	
	public function take($transition = NULL)
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Cannot take transition in terminated %s', $this));
		}
		
		$this->engine->pushCommand(new CallbackCommand(function() use($transition) {
			
			if($transition instanceof Transition)
			{
				$transition = $transition->getId();
			}
			
			$trans = NULL;
			$out = (array)$this->getProcessDefinition()->findOutgoingTransitions($this->node->getId());
			
			if($transition === NULL)
			{
				if(count($out) != 1)
				{
					throw new \RuntimeException(sprintf('No single outgoing transition found at node "%s"', $this->node->getId()));
				}
					
				$trans = array_pop($out);
			}
			else
			{
				foreach($out as $tmp)
				{
					if($tmp->getId() === $transition)
					{
						$trans = $tmp;
						break;
					}
				}
					
				if($trans === NULL)
				{
					throw new \RuntimeException(sprintf('Transition "%s" not connected to node "%s"', $transition, $this->node->getId()));
				}
			}
			
			if(!$trans->isEnabled($this))
			{
				$this->terminate();
				
				if($this->isConcurrent() && 0 == count($this->findConcurrentExecutions()))
				{
					$this->parentExecution->setActive(true);
						
					return $this->parentExecution->terminate();
				}
				
				return;
			}
			
			$this->engine->debug('{0} leaves node {1}', [(string)$this, (string)$this->node]);
			$this->engine->notify(new LeaveNodeEvent($this->node, $this));
			
			$this->engine->debug('{0} taking transition {1}', [(string)$this, (string)$trans]);
			$this->engine->notify(new TakeTransitionEvent($trans, $this));
			
			$this->timestamp = microtime(true);
			$this->transition = $trans;
			
			$this->execute($this->getProcessDefinition()->findNode($trans->getTo()));
		}));
	}
	
	/**
	 * Take all given transitions (or every transition when given NULL) recycling the given
	 * executions in the process.
	 * 
	 * @param array<string> $transitions
	 * @param array<Execution> $recycle
	 * 
	 * @throws \RuntimeException
	 */
	public function takeAll(array $transitions = NULL, array $recycle = [])
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Cannot take transition in terminated %s', $this));
		}
		
		$this->engine->pushCommand(new CallbackCommand(function() use($transitions, $recycle) {
			
			if($transitions === NULL)
			{
				$transitions = $this->getProcessDefinition()->findOutgoingTransitions($this->node->getId());
			}
			else
			{
				$transitions = array_map(function($trans) {
					return ($trans instanceof Transition) ? $trans : $this->getProcessDefinition()->findTransition($trans);
				}, $transitions);
			}
			
			$transitions = array_filter($transitions, function(Transition $trans) {
				return $trans->isEnabled($this);
			});
			
			if(!in_array($this, $recycle, true))
			{
				array_unshift($recycle, $this);
			}
			
			if(empty($transitions))
			{
				foreach($recycle as $rec)
				{
					$rec->terminate();
				}
					
				if(!$this->isTerminated())
				{
					$this->terminate();
				}
					
				if($this->isConcurrent() && 0 == count($this->findConcurrentExecutions()))
				{
					$this->parentExecution->setActive(true);
			
					return $this->parentExecution->terminate();
				}
					
				return;
			}
			
			$root = $this->isConcurrent() ? $this->parentExecution : $this;
			$merge = count($root->findChildExecutions()) == count($root->findChildExecutions($this->node));
			$active = [];
			
			foreach($root->findChildExecutions() as $exec)
			{
				if($exec->isActive())
				{
					$active[] = $exec;
				}
			}
			
			$recycle = array_filter($recycle, function(Execution $execution) use($root) {
				return $execution !== $root;
			});
			
			if(count($transitions) == 1 && empty($active) && $merge)
			{
				$terminated = 0;
				
				foreach($recycle as $rec)
				{
					if(!$rec->isTerminated())
					{
						$rec->terminate();
						
						$terminated++;
					}
				}
					
				$root->setNode($this->node);
				$this->setState(self::STATE_CONCURRENT, false);
				$root->setActive(true);
					
				$this->engine->debug(sprintf('Merged %u concurrent executions into {0}', $terminated), [(string)$root]);
				
				return $root->take(array_shift($transitions));
			}
		
			$outgoing = [];
		
			while(!empty($transitions))
			{
				$transition = array_shift($transitions);
					
				if(empty($recycle))
				{
					$exec = $root->createExecution(true);
				}
				else
				{
					$exec = array_shift($recycle);
				}
					
				$exec->setActive(true);
				$exec->setNode($this->node);
				$exec->setState(self::STATE_CONCURRENT, true);
				$outgoing[] = [$exec, $transition];
			}
		
			$root->setActive(false);
		
			foreach($recycle as $rec)
			{
				if(!$rec->isTerminated())
				{
					$rec->terminate();
				}
			}
		
			foreach($outgoing as $out)
			{
				$out[0]->take($out[1]);
			}
		}));
	}
	
	protected function hasState($state)
	{
		return $state === ($this->state & $state);
	}
	
	protected function hasAnyState($state)
	{
		return 0 != ($this->state & $state);
	}
	
	protected function setState($state, $flag)
	{
		if($flag)
		{
			$this->state |= $state;
		}
		else
		{
			$this->state = ($this->state | $state) ^ $state;
		}
	}
}
