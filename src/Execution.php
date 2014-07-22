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

use KoolKode\Expression\ExpressionContextInterface;
use KoolKode\Process\Event\EndProcessEvent;
use KoolKode\Process\Event\EnterNodeEvent;
use KoolKode\Process\Event\LeaveNodeEvent;
use KoolKode\Process\Event\TakeTransitionEvent;
use KoolKode\Util\UUID;

/**
 * A path of execution holds state and can be thought of as a token in process diagram or Petri-Net.
 * 
 * @author Martin Schröder
 */
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
	
	public function __construct(UUID $id, EngineInterface $engine, ProcessDefinition $processDefinition, Execution $parentExecution = NULL)
	{
		$this->id = $id;
		$this->engine = $engine;
		$this->processDefinition = $processDefinition;
		$this->parentExecution = $parentExecution;
		
		if($parentExecution === NULL)
		{
			$this->state |= self::STATE_SCOPE;
			
			$engine->debug('Starting process {0}', [$processDefinition->getTitle()]);
		}
		
		$engine->debug('Created {0}', [(string)$this]);
	}
	
	public function __toString()
	{
		if($this->parentExecution === NULL)
		{
			return sprintf('process(%s)', $this->id);
		}
		
		return sprintf('execution(%s)', $this->id);
	}

	/**
	 * Get the globally unique identifier of this execution.
	 * 
	 * @return UUID
	 */
	public function getId()
	{
		return $this->id;
	}
	
	/**
	 * Get the process engine being used to automate the execution.
	 * 
	 * @return EngineInterface
	 */
	public function getEngine()
	{
		return $this->engine;
	}
	
	/**
	 * Create an expression context bound to this execution.
	 * 
	 * @return ExpressionContextInterface
	 */
	public function getExpressionContext()
	{
		return $this->engine->getExpressionContextFactory()->createContext($this);
	}
	
	/**
	 * Check if the path of execution has been terminated.
	 * 
	 * @return boolean
	 */
	public function isTerminated()
	{
		return 0 != ($this->state & self::STATE_TERMINATE);
	}
	
	/**
	 * Terminate this path of execution, will also terminate all child executions.
	 */
	public function terminate()
	{
		$this->state |= self::STATE_TERMINATE;
		
		$this->engine->debug('Terminate {0}', [(string)$this]);
		
		foreach($this->childExecutions as $execution)
		{
			if(!$execution->isTerminated())
			{
				$execution->terminate();
			}
		}
		
		if($this->parentExecution === NULL)
		{
			$this->engine->notify(new EndProcessEvent($this->node, $this));
		}
		else
		{
			$this->parentExecution->childExecutionTerminated($this);
		}
	}
	
	/**
	 * Is being used to notify a parent execution whenever a child execution has been terminated.
	 * 
	 * @param Execution $execution
	 */
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
	
	/**
	 * Get the internal state of this execution.
	 * 
	 * @return string
	 */
	public function getState()
	{
		return $this->state;
	}
	
	/**
	 * Check if the path of execution is active.
	 * 
	 * @return boolean
	 */
	public function isActive()
	{
		return 0 != ($this->state & self::STATE_ACTIVE);
	}
	
	/**
	 * Toggle active flag of this path of execution.
	 * 
	 * @param boolean $active
	 */
	public function setActive($active)
	{
		$this->setState(self::STATE_ACTIVE, $active);
	}
	
	/**
	 * Get microtime timestamp of the last activity being performed within this path
	 * of execution.
	 * 
	 * @return float
	 */
	public function getTimestamp()
	{
		return $this->timestamp;
	}
	
	/**
	 * Check if this path of execution is a concurrent execution.
	 * 
	 * @return boolean
	 */
	public function isConcurrent()
	{
		return 0 != ($this->state & self::STATE_CONCURRENT);
	}
	
	/**
	 * Check if this execution is waiting for a signal.
	 * 
	 * @return boolean
	 */
	public function isWaiting()
	{
		return 0 != ($this->state & self::STATE_WAIT);
	}
	
	/**
	 * Check if this execution is a scope for local variables.
	 * 
	 * @return boolean
	 */
	public function isScope()
	{
		return 0 != ($this->state & self::STATE_SCOPE);
	}
	
	/**
	 * Get the parent execution of this execution.
	 * 
	 * @return Execution or NULL if this execution is a root execution.
	 */
	public function getParentExecution()
	{
		return $this->parentExecution;
	}
	
	/**
	 * Create a new child execution and register it with the process engine.
	 * 
	 * @param boolean $concurrent
	 * @return Execution
	 */
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
	
	/**
	 * Find all child executions.
	 * 
	 * @param Node $node Optional filter, return only executions that have arrived at the given node.
	 * @return array<Execution> All matching child executions.
	 */
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
	
	/**
	 * Find all concurrent executions (e.g. any execution runs "in parallel" to this execution).
	 *
	 * @param Node $node Optional filter, return only executions that have arrived at the given node.
	 * @return array<Execution> All matching concurrent executions.
	 */
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
	
	/**
	 * Find all inactive concurrent executions (e.g. any execution runs "in parallel" to this execution).
	 *
	 * @param Node $node Optional filter, return only executions that have arrived at the given node.
	 * @return array<Execution> All matching inactive concurrent executions.
	 */
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
	
	/**
	 * Find all waiting child executions.
	 * 
	 * @param Node $node Optional filter, return only executions that have arrived at the given node.
	 * @return array<Execution> All matching waiting child executions.
	 */
	public function findWaitingExecutions(Node $node = NULL)
	{
		return array_filter($this->findChildExecutions($node), function(Execution $execution) {
			return $execution->isActive() && $execution->isWaiting();
		});
	}
	
	/**
	 * Check if this execution is a root execution (resembles a "process instance").
	 * 
	 * @return boolean
	 */
	public function isRootExecution()
	{
		return $this->parentExecution === NULL;
	}
	
	/**
	 * Get the root execution (could be refered to as a "process instance").
	 * 
	 * @return Execution
	 */
	public function getRootExecution()
	{
		if($this->parentExecution === NULL)
		{
			return $this;
		}
		
		return $this->parentExecution->getRootExecution();
	}
	
	/**
	 * Check if the given variable is set in the current scope.
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasVariable($name)
	{
		if($this->state & self::STATE_SCOPE)
		{
			return array_key_exists($name, $this->variables);
		}
		
		return $this->parentExecution->hasVariable($name);
	}
	
	/**
	 * Get the value of the given variable in the current scope.
	 * 
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 * 
	 * @throws \OutOfBoundsException When the variable is not set and no default value is given.
	 */
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
	
	/**
	 * Set the given variable in the current scope, setting a variable to a value of NULL will
	 * remove the variable from the current scope.
	 * 
	 * @param string $name
	 * @param mixed $value
	 */
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
	
	/**
	 * Remove the given variable from the current scope.
	 * 
	 * @param string $name
	 */
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
	
	/**
	 * Get the current node that this execution has arrived at.
	 * 
	 * @return Node
	 */
	public function getNode()
	{
		return $this->node;
	}
	
	/**
	 * Set the current node of this execution.
	 * 
	 * @param Node $node
	 */
	public function setNode(Node $node = NULL)
	{
		$this->node = $node;
	}
	
	/**
	 * Check if there has been a transition being taken.
	 * 
	 * @return boolean
	 */
	public function hasTransition()
	{
		return $this->transition !== NULL;
	}
	
	/**
	 * Get the last transition that has been taken.
	 * 
	 * @return Transition Last transition or NULL if no transition has been taken.
	 */
	public function getTransition()
	{
		return $this->transition;
	}
	
	/**
	 * Get the definition of the process being executed.
	 * 
	 * @return ProcessDefinition
	 */
	public function getProcessDefinition()
	{
		return $this->processDefinition;
	}
	
	/**
	 * Register commands to execute the behavior of the given node.
	 * 
	 * @param Node $node
	 * 
	 * @throws \RuntimeException When the execution has been terminated.
	 */
	public function execute(Node $node)
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('%s is terminated', $this));
		}
		
		$this->engine->pushCommand(new CallbackCommand(function(EngineInterface $engine) use($node) {
			
			$this->timestamp = microtime(true);
			$this->node = $node;
			
			$this->engine->debug('{0} entering {1}', [(string)$this, (string)$this->node]);
			$this->engine->notify(new EnterNodeEvent($this->node, $this));
			
			$this->node->getBehavior()->execute($this);
		}));
	}
	
	/**
	 * Put the current execution into a wait state.
	 * 
	 * @throws \RuntimeException When the execution has been terminated.
	 */
	public function waitForSignal()
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Terminated %s cannot enter wait state', $this));
		}
		
		$this->timestamp = microtime(true);
		$this->state |= self::STATE_WAIT;
		
		$this->engine->debug('{0} entered wait state', [(string)$this]);
	}
	
	/**
	 * Signal the execution in order to continue from a wait state.
	 * 
	 * @param string $signal The name of the signal or NULL when no such name exists.
	 * @param array<string, mixed> $variables Execution variables to be set.
	 * 
	 * @throws \RuntimeException When the execution is terminated or not within a wait state.
	 */
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
			
			$behavior = $this->node->getBehavior();
			
			if($behavior instanceof SignalableBehaviorInterface)
			{
				$behavior->signal($this, $signal, $variables);
			}
			else
			{
				$this->takeAll(NULL, [$this]);
			}
		}));
		
	}
	
	/**
	 * Take a transition from the given node.
	 * 
	 * @param string $transition ID of the transition, ommitting it will assume a single outgoing transition.
	 * 
	 * @throws \RuntimeException When the execution has been terminated.
	 */
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
			
			$this->engine->debug('{0} leaves {1}', [(string)$this, (string)$this->node]);
			$this->engine->notify(new LeaveNodeEvent($this->node, $this));
			
			$this->engine->debug('{0} transitions to {1}', [(string)$this, (string)$trans]);
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
	 * @throws \RuntimeException When the execution has been terminated.
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
					
				if($terminated > 0)
				{
					$this->engine->debug(sprintf('Merged %u concurrent executions into {0}', $terminated), [(string)$root]);
				}
				
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
