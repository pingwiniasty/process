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
use KoolKode\Process\Behavior\SignalableBehaviorInterface;
use KoolKode\Process\Command\CallbackCommand;
use KoolKode\Process\Command\ExecuteNodeCommand;
use KoolKode\Process\Command\SignalExecutionCommand;
use KoolKode\Process\Event\CreateExpressionContextEvent;
use KoolKode\Process\Event\EndProcessEvent;
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
	const KEY_EXECUTION = '@execution';
	
	const STATE_NONE = 0;
	const STATE_WAIT = 1;
	const STATE_SCOPE = 2;
	const STATE_ACTIVE = 4;
	const STATE_CONCURRENT = 8;
	const STATE_TERMINATE = 16;
	const STATE_SCOPE_ROOT = 32;
	
	const SYNC_STATE_NO_CHANGE = 0;
	const SYNC_STATE_MODIFIED = 1;
	const SYNC_STATE_REMOVED = 2;
	
	protected $id;
	protected $state = self::STATE_ACTIVE;
	protected $syncState = self::SYNC_STATE_NO_CHANGE;
	protected $syncData = [];
	
	protected $timestamp = 0;
	protected $variables = [];
	
	protected $model;
	protected $transition;
	protected $node;
	
	protected $parentExecution;
	protected $childExecutions = [];
	
	protected $engine;
	
	public function __construct(UUID $id, EngineInterface $engine, ProcessModel $model, Execution $parentExecution = NULL)
	{
		$this->id = $id;
		$this->engine = $engine;
		$this->model = $model;
		$this->parentExecution = $parentExecution;
		
		if($parentExecution === NULL)
		{
			$this->state |= self::STATE_SCOPE | self::STATE_SCOPE_ROOT;
		}
		else
		{
			$parentExecution->registerChildExecution($this);
		}
	}
	
	public function __toString()
	{
		if($this->parentExecution === NULL)
		{
			return sprintf('process(%s)', $this->id);
		}
		
		return sprintf('execution(%s)', $this->id);
	}
	
	public function __debugInfo()
	{
		$data = get_object_vars($this);
		
		unset($data['engine']);
		unset($data['model']);
		
		if($this->parentExecution instanceof Execution)
		{
			$data['parentExecution'] = $this->parentExecution->getId();
		}
		
		$data['childExecutions'] = array_map(function(Execution $exec) {
			return $exec->getId();
		}, $this->childExecutions);
		
		return $data;
	}
	
	public function getSyncState()
	{
		return $this->syncState;
	}
	
	public function setSyncState($state)
	{
		switch((int)$state)
		{
			case self::SYNC_STATE_MODIFIED:
			case self::SYNC_STATE_NO_CHANGE:
			case self::SYNC_STATE_REMOVED:
				// OK
				break;
			default:
				throw new \InvalidArgumentException(sprintf('Invalid sync state: %s', $state));
		}
		
		$this->syncState = (int)$state;
	}
	
	public function getSyncData()
	{
		return $this->syncData;
	}
	
	public function setSyncData(array $data)
	{
		$this->syncData = $data;
	}
	
	public function collectSyncData()
	{
		$data = [
			'id' => $this->id,
			'parentId' => ($this->parentExecution === NULL) ? NULL : $this->parentExecution->getId(),
			'processId' => $this->getRootExecution()->getId(),
			'modelId' => $this->model->getId(),
			'state' => $this->state,
			'depth' => $this->getExecutionDepth(),
			'timestamp' => $this->timestamp,
			'variables' => $this->variables,
			'transition' => ($this->transition === NULL) ? NULL : (string)$this->transition->getId(),
			'node' => ($this->node === NULL) ? NULL : (string)$this->node->getId()
		];
		
		return $data;
	}
	
	public function getExecutionDepth()
	{
		return ($this->parentExecution === NULL) ? 0 : $this->parentExecution->getExecutionDepth() + 1;
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
		$access = new ExecutionAccess($this);
		$this->engine->notify(new CreateExpressionContextEvent($access));
		
		return $this->engine->getExpressionContextFactory()->createContext($access);
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
	 * 
	 * @param boolean $triggerExecution Trigger / signal parent execution after termination?
	 */
	public function terminate($triggerExecution = true)
	{
		$this->state |= self::STATE_TERMINATE;
		$this->syncState = self::SYNC_STATE_REMOVED;
		
		$this->engine->debug('Terminate {execution}', [
			'execution' => (string)$this
		]);
		
		foreach($this->childExecutions as $execution)
		{
			if(!$execution->isTerminated())
			{
				$execution->terminate(false);
			}
		}
		
		if($this->parentExecution === NULL)
		{
			$this->engine->notify(new EndProcessEvent($this->node, $this));
		}
		else
		{
			$this->parentExecution->childExecutionTerminated($this, $triggerExecution);
		}
	}
	
	/**
	 * Register the given child execution with the parent execution.
	 * 
	 * @param Execution $execution
	 * @return Execution
	 */
	protected function registerChildExecution(Execution $execution)
	{
		if(!in_array($execution, $this->childExecutions, true))
		{
			$this->childExecutions[] = $execution;
		}
		
		$this->markModified();
		
		return $execution;
	}
	
	/**
	 * Is being used to notify a parent execution whenever a child execution has been terminated.
	 * 
	 * @param Execution $execution
	 * @param boolean $triggerExecution Trigger / signal this execution?
	 */
	protected function childExecutionTerminated(Execution $execution, $triggerExecution = true)
	{
		$removed = false;
		$scope = $execution->isScope();
		
		foreach($this->childExecutions as $index => $exec)
		{
			if($exec === $execution)
			{
				unset($this->childExecutions[$index]);
				$removed = true;
		
				break;
			}
		}
		
		if(empty($this->childExecutions) && $scope && $removed && $triggerExecution)
		{
			if($this->isWaiting())
			{
				$this->signal(NULL, [self::KEY_EXECUTION => $execution]);
			}
			else
			{
				$this->takeAll(NULL, [$this]);
			}
		}
		
		$this->markModified();
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
		
		$this->markModified();
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
	
	public function setTimestamp($timestamp)
	{
		$this->timestamp = (float)$timestamp;
		
		$this->markModified();
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
	 * Check if this execution is a scope for root variables.
	 *
	 * @return boolean
	 */
	public function isScopeRoot()
	{
		return 0 != ($this->state & self::STATE_SCOPE_ROOT);
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
		$execution = new static(UUID::createRandom(), $this->engine, $this->model, $this);
		$execution->setNode($this->node);
		
		if($concurrent)
		{
			$execution->state |= self::STATE_CONCURRENT;
		}
		
		$this->engine->registerExecution($execution);
		$this->engine->debug(sprintf('Created %s{execution} from {parent}', $concurrent ? 'concurrent ' : ''), [
			'execution' => (string)$execution,
			'parent' => (string)$this
		]);
		
		$this->markModified();
		
		return $execution;
	}
	
	/**
	 * Create a nested execution as child execution.
	 * 
	 * @param ProcessModel $model
	 * @param boolean $isRootScope
	 * @return Execution
	 */
	public function createNestedExecution(ProcessModel $model, $isRootScope = true)
	{
		$execution = new static(UUID::createRandom(), $this->engine, $model, $this);
		$execution->setState(self::STATE_SCOPE, true);
		$execution->setState(self::STATE_SCOPE_ROOT, $isRootScope);
		
		$this->engine->registerExecution($execution);
		$this->engine->debug('Created nested {execution} from {parent}', [
			'execution' => (string)$execution,
			'parent' => (string)$this
		]);
		
		$this->markModified();
		
		return $execution;
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
	 * Get the execution that is the root scope of this execution.
	 * 
	 * @return Execution
	 */
	public function getScopeRoot()
	{
		if($this->parentExecution === NULL || $this->state & self::STATE_SCOPE_ROOT)
		{
			return $this;
		}
		
		return $this->parentExecution->getScopeRoot();
	}
	
	/**
	 * Check if the given variable is set in the current scope.
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasVariable($name)
	{
		return $this->getScopeRoot()->hasVariableLocal($name);
	}
	
	/**
	 * Check if the given variable is set in the current scope.
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function hasVariableLocal($name)
	{
		return array_key_exists($name, $this->variables);
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
		if(func_num_args() > 1)
		{
			return $this->getScopeRoot()->getVariableLocal($name, func_get_arg(1));
		}
		
		return $this->getScopeRoot()->getVariableLocal($name);
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
	public function getVariableLocal($name)
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
	
	/**
	 * Set the given variable in the current scope, setting a variable to a value of NULL will
	 * remove the variable from the current scope.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setVariable($name, $value)
	{
		return $this->getScopeRoot()->setVariableLocal($name, $value);
	}
	
	/**
	 * Set the given variable in the current scope, setting a variable to a value of NULL will
	 * remove the variable from the current scope.
	 * 
	 * @param string $name
	 * @param mixed $value
	 */
	public function setVariableLocal($name, $value)
	{
		if($value === NULL)
		{
			return $this->removeVariableLocal($name);
		}
		
		$this->variables[$name] = $value;
		
		$this->engine->debug('Set variable {var} in {execution}', [
			'var' => (string)$name,
			'execution' => (string)$this
		]);
		
		$this->markModified();
	}
	
	/**
	 * Remove the given variable from the current scope.
	 *
	 * @param string $name
	 */
	public function removeVariable($name)
	{
		return $this->getScopeRoot()->removeVariableLocal($name);
	}
	
	/**
	 * Remove the given variable from the current scope.
	 * 
	 * @param string $name
	 */
	public function removeVariableLocal($name)
	{
		unset($this->variables[$name]);
		
		$this->engine->debug('Removed variable {var} from {execution}', [
			'var' => (string)$name,
			'execution' => (string)$this
		]);
		
		$this->markModified();
	}
	
	public function getVariablesLocal()
	{
		return (array)$this->variables;
	}
	
	public function setVariablesLocal(array $variables)
	{
		$this->variables = $variables;
		
		$this->markModified();
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
		
		$this->markModified();
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
	 * Get the model of the process being executed.
	 * 
	 * @return ProcessModel
	 */
	public function getProcessModel()
	{
		return $this->model;
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
		
		$this->engine->pushCommand(new ExecuteNodeCommand($this, $node));
	}
	
	/**
	 * Put the execution into a wait state.
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
		$this->setState(self::STATE_WAIT, true);
		
		$this->engine->debug('{execution} entered wait state', [
			'execution' => (string)$this
		]);
		
		$this->markModified();
	}
	
	/**
	 * Wake the execution up from a wait state.
	 * 
	 * @throws \RuntimeException When the execution has been terminated.
	 */
	public function wakeUp()
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Terminated %s cannot enter wait state', $this));
		}
		
		$this->timestamp = microtime(true);
		$this->setState(self::STATE_WAIT, false);
		
		$this->engine->debug('{execution} woke up from wait state', [
			'execution' => (string)$this
		]);
		
		$this->markModified();
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
		
		$this->engine->pushCommand(new SignalExecutionCommand($this, $signal, $variables));
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
			
			if($this->isConcurrent())
			{
				if(1 === count($this->findConcurrentExecutions()))
				{
					$this->terminate();
			
					$this->parentExecution->node = $this->node;
					$this->parentExecution->transition = $this->transition;
					$this->parentExecution->setActive(true);
					
					$this->engine->debug('Merged concurrent {execution} into {root}', [
						'execution' => (string)$this,
						'root' => (string)$this->parentExecution
					]);
			
					return $this->parentExecution->take($transition);
				}
			}
			
			if($transition instanceof Transition)
			{
				$transition = $transition->getId();
			}
			
			$trans = NULL;
			$out = (array)$this->getProcessModel()->findOutgoingTransitions($this->node->getId());
			
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
			
			$this->engine->debug('{execution} leaves {node}', [
				'execution' => (string)$this,
				'node' => (string)$this->node
			]);
			$this->engine->notify(new LeaveNodeEvent($this->node, $this));
			
			$this->engine->debug('{execution} taking {transition}', [
				'execution' => (string)$this,
				'transition' => (string)$trans
			]);
			$this->engine->notify(new TakeTransitionEvent($trans, $this));
			
			$this->timestamp = microtime(true);
			$this->transition = $trans;
			
			$this->markModified();
			
			$this->execute($this->getProcessModel()->findNode($trans->getTo()));
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
			
			if($this->isConcurrent())
			{
				if(1 === count($this->findConcurrentExecutions()))
				{
					foreach($recycle as $rec)
					{
						foreach($recycle as $rec)
						{
							$rec->terminate();
						}
							
						if(!$this->isTerminated())
						{
							$this->terminate();
						}
					}
						
					$this->parentExecution->node = $this->node;
					$this->parentExecution->transition = $this->transition;
					$this->parentExecution->setActive(true);
					
					$this->engine->debug('Merged concurrent {execution} into {root}', [
						'execution' => (string)$this,
						'root' => (string)$this->parentExecution
					]);
						
					return $this->parentExecution->takeAll($transitions);
				}
			}
			
			if($transitions === NULL)
			{
				$transitions = $this->getProcessModel()->findOutgoingTransitions($this->node->getId());
			}
			else
			{
				$transitions = array_map(function($trans) {
					return ($trans instanceof Transition) ? $trans : $this->getProcessModel()->findTransition($trans);
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
					$this->engine->debug('Merged {count} concurrent executions into {execution}', [
						'count' => $terminated,
						'execution' => (string)$root
					]);
				}
				
				$this->markModified();
				
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
	
	/**
	 * Introduce a new concurrent root as parent of this execution.
	 * 
	 * @param boolean $active
	 * @return Execution
	 */
	public function introduceConcurrentRoot($active = false)
	{
		$this->engine->debug('Introducing concurrent root into {execution}', [
			'execution' => (string)$this
		]);
		
		$parent = $this->parentExecution;
	
		$root = new static(UUID::createRandom(), $this->engine, $this->model, $parent);
		$root->setActive($active);
		$root->variables = $this->variables;
		$root->setState(self::STATE_SCOPE, true);
		$root->setState(self::STATE_SCOPE_ROOT, $this->isScopeRoot());
	
		if($parent !== NULL)
		{
			foreach($parent->childExecutions as $index => $exec)
			{
				if($exec === $this)
				{
					unset($parent->childExecutions[$index]);
				}
			}
		}
	
		$this->setParentExecution($root);
		$this->setState(self::STATE_CONCURRENT, true);
		$this->setState(self::STATE_SCOPE, false);
		$this->setState(self::STATE_SCOPE_ROOT, false);
		$this->variables = [];
	
		$this->markModified();
		
		$this->engine->registerExecution($root);
		
		return $root;
	}
	
	protected function markModified()
	{
		if($this->syncState !== self::SYNC_STATE_REMOVED)
		{
			$this->syncState = self::SYNC_STATE_MODIFIED;
		}
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
