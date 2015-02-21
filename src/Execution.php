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
use KoolKode\Process\Event\CreateExpressionContextEvent;
use KoolKode\Process\Event\EndProcessEvent;
use KoolKode\Util\UUID;

/**
 * A path of execution holds state and can be thought of as a token in process diagram or Petri-Net.
 * 
 * @author Martin Schröder
 */
class Execution
{
	/**
	 * State: No state set.
	 * 
	 * @var integer
	 */
	const STATE_NONE = 0;
	
	/**
	 * State: Execution is waiting for a signal.
	 * 
	 * @var integer
	 */
	const STATE_WAIT = 1;
	
	/**
	 * State: Execution is a variable scope.
	 *
	 * @var integer
	 */
	const STATE_SCOPE = 2;
	
	/**
	 * State: Execution is active.
	 *
	 * @var integer
	 */
	const STATE_ACTIVE = 4;
	
	/**
	 * State: Execution is a concurrent child execution.
	 *
	 * @var integer
	 */
	const STATE_CONCURRENT = 8;
	
	/**
	 * State: Execution has been terminated.
	 *
	 * @var integer
	 */
	const STATE_TERMINATE = 16;
	
	/**
	 * State:Execution is an independent root scope (a nested process without shared variables
	 * likely even using a different process model).
	 *
	 * @var integer
	 */
	const STATE_SCOPE_ROOT = 32;
	
	/**
	 * Sync state: Nothing changed.
	 * 
	 * @var integer
	 */
	const SYNC_STATE_NO_CHANGE = 0;
	
	/**
	 * Sync state: Execution has been modified.
	 *
	 * @var integer
	 */
	const SYNC_STATE_MODIFIED = 1;
	
	/**
	 * Sync state: Execution has been removed / terminated.
	 *
	 * @var integer
	 */
	const SYNC_STATE_REMOVED = 2;
	
	/**
	 * Unique execution ID.
	 * 
	 * @var UUID
	 */
	protected $id;
	
	/**
	 * Current state of the execution, bitwise combination of Execution::STATE_* constants.
	 * 
	 * @var integer
	 */
	protected $state = self::STATE_ACTIVE;
	
	/**
	 * Current sync state of the execution, one of the Execution::SYNC_STATE_* constants.
	 * 
	 * @var integer
	 */
	protected $syncState = self::SYNC_STATE_NO_CHANGE;
	
	/**
	 * Snapshot of the execution's state after the last sync operation.
	 * 
	 * @var array
	 */
	protected $syncData = [];
	
	/**
	 * Timestamp (including millis) of the last activity being performed by the execution.
	 * 
	 * @var float
	 */
	protected $timestamp = 0;
	
	/**
	 * Local scope variables, must be empty if the execution is not a scope.
	 * 
	 * @var array<string, mixed>
	 */
	protected $variables = [];
	
	/**
	 * Holds a reference to the model of the process being executed.
	 * 
	 * @var ProcessModel
	 */
	protected $model;
	
	/**
	 * The last transition being taken by the execution.
	 * 
	 * @var Transition
	 */
	protected $transition;
	
	/**
	 * The current node the execution is positioned at.
	 * 
	 * @var Node
	 */
	protected $node;
	
	/**
	 * Holds a reference to the parent execution, or NULL if this execution is the root of an execution tree.
	 * 
	 * @var Execution
	 */
	protected $parentExecution;
	
	/**
	 * Holds references to all nested child executions.
	 * 
	 * @var array<Execution>
	 */
	protected $childExecutions = [];
	
	/**
	 * The process engine being used to automate the process.
	 * 
	 * @var EngineInterface
	 */
	protected $engine;
	
	public function __construct(UUID $id, EngineInterface $engine, ProcessModel $model, Execution $parentExecution = NULL)
	{
		$this->id = $id;
		$this->engine = $engine;
		$this->model = $model;
		$this->parentExecution = $parentExecution;
		
		if($parentExecution === NULL)
		{
			$this->state |= self::STATE_SCOPE;
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
	
	/**
	 * Get the current sync state.
	 * 
	 * @return integer One of the Execution::SYNC_STATE_* constants.
	 */
	public function getSyncState()
	{
		return $this->syncState;
	}
	
	/**
	 * Set the current sync state.
	 * 
	 * @param integer $state One of the Execution::SYNC_STATE_* constants.
	 * 
	 * @throws \InvalidArgumentException When an invalid sync state value has been given.
	 */
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
	
	/**
	 * Get the last sync data related to the execution.
	 * 
	 * @return array
	 */
	public function getSyncData()
	{
		return $this->syncData;
	}
	
	/**
	 * Set last sync data for this execution.
	 * 
	 * @param array $data
	 */
	public function setSyncData(array $data)
	{
		$this->syncData = $data;
	}
	
	/**
	 * Compute current sync data from execution state.
	 * 
	 * @return array
	 */
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
	
	/**
	 * Compute current execution depth.
	 * 
	 * @return number
	 */
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
		if($this->hasState(self::STATE_TERMINATE))
		{
			return;
		}
		
		$this->state |= self::STATE_TERMINATE;
		$this->syncState = self::SYNC_STATE_REMOVED;
		
		$this->engine->debug('Terminate {execution}', [
			'execution' => (string)$this
		]);
		
		foreach($this->childExecutions as $execution)
		{
			$execution->terminate(false);
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
	public function registerChildExecution(Execution $execution)
	{
		$execution->parentExecution = $this;
		
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
		$scope = $execution->isScope() || !$execution->isConcurrent();
		
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
				$this->signal(NULL, [], ['executionId' => $execution->getId()]);
			}
			else
			{
				$this->takeAll(NULL, [$this]);
			}
		}
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
	 * Set concurrent state of the execution.
	 * 
	 * @param boolean $concurrent
	 */
	public function setConcurrent($concurrent)
	{
		$this->setState(self::STATE_CONCURRENT, $concurrent);
		
		$this->markModified();
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
		if($this->parentExecution === NULL)
		{
			return true;
		}
		
		return 0 != ($this->state & self::STATE_SCOPE);
	}
	
	/**
	 * Toggle scope of the execution.
	 *
	 * @param boolean $scope
	 */
	public function setScope($scope)
	{
		$this->setState(self::STATE_SCOPE, $scope);
		
		if(!$scope)
		{
			$this->variables = [];
		}
		
		$this->markModified();
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
	 * Created executions are unscoped by default.
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
		
		return $execution;
	}
	
	/**
	 * Create a nested execution as child execution.
	 * 
	 * @param ProcessModel $model
	 * @param boolean $isScope
	 * @param boolean $isScopeRoot
	 * @return Execution
	 */
	public function createNestedExecution(ProcessModel $model, $isScope = true, $isScopeRoot = false)
	{
		$execution = new static(UUID::createRandom(), $this->engine, $model, $this);
		$execution->setState(self::STATE_SCOPE, $isScope);
		$execution->setState(self::STATE_SCOPE_ROOT, $isScopeRoot);
		
		$this->engine->registerExecution($execution);
		$this->engine->debug('Created nested {execution} from {parent}', [
			'execution' => (string)$execution,
			'parent' => (string)$this
		]);
		
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
	 * Get the variable scope of this execution (not necessarily the execution itself).
	 * 
	 * @return Execution
	 */
	public function getScope()
	{
		$exec = $this;
		
		while(!$exec->isScope() && !$exec->isScopeRoot())
		{
			$exec = $exec->getParentExecution();
		}
		
		return $exec;
	}
	
	/**
	 * Check if this execution is scope root (process instance or nested execution with isolated scope).
	 * 
	 * @return boolean
	 */
	public function isScopeRoot()
	{
		if($this->parentExecution === NULL)
		{
			return true;
		}
		
		return $this->hasState(self::STATE_SCOPE_ROOT);
	}
	
	/**
	 * Get the closest scope root starting form the execution.
	 * 
	 * @return Execution
	 */
	public function getScopeRoot()
	{
		$exec = $this;
		
		while(!$exec->isScopeRoot())
		{
			$exec = $exec->getParentExecution();
		}
		
		return $exec;
	}
	
	/**
	 * Fetch all variables visible to the execution.
	 * 
	 * @return array<string, mixed>
	 */
	protected function computeVariables()
	{
		if($this->isScopeRoot())
		{
			return $this->variables;
		}
		
		if($this->isScope())
		{
			return array_merge($this->parentExecution->computeVariables(), $this->variables);
		}
		
		return $this->parentExecution->computeVariables();
	}
	
	/**
	 * Check if the given variable is avialable (eighter in the scope or inherited).
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasVariable($name)
	{
		return array_key_exists($name, $this->computeVariables());
	}
	
	/**
	 * Check if the given variable is set in the scope of this execution.
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function hasVariableLocal($name)
	{
		return array_key_exists($name, $this->getScope()->variables);
	}
	
	/**
	 * Get the value of the given variable eighter from the current scope or inherited.
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 *
	 * @throws \OutOfBoundsException When the variable is not set and no default value is given.
	 */
	public function getVariable($name)
	{
		$vars = $this->computeVariables();
		
		if(array_key_exists($name, $vars))
		{
			return $vars[$name];
		}
		
		if(func_num_args() > 1)
		{
			return func_get_arg(1);
		}
		
		throw new \OutOfBoundsException(sprintf('Variable "%s" neighter set nor inherited in scope %s', $name, $this));
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
		$vars = $this->getScope()->variables;
	
		if(array_key_exists($name, $vars))
		{
			return $vars[$name];
		}
	
		if(func_num_args() > 1)
		{
			return func_get_arg(1);
		}
	
		throw new \OutOfBoundsException(sprintf('Variable "%s" not found in scope %s', $name, $this));
	}
	
	/**
	 * Set the given variable in the scope root, passing a value of NULL will remove the variable.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setVariable($name, $value)
	{
		if($value === NULL)
		{
			return $this->removeVariable($name);
		}
		
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
		if(!$this->isScope())
		{
			return $this->getScope()->setVariableLocal($name, $value);
		}
		
		if($value === NULL)
		{
			unset($this->variables[(string)$name]);
		}
		else
		{
			$this->variables[(string)$name] = $value;
		}
		
		$this->markModified();
	}
	
	/**
	 * Remove a variable, will remove the variable from all parent scopes up to the scope root.
	 * 
	 * @param string $name
	 */
	public function removeVariable($name)
	{
		$exec = $this;
		
		while($exec !== NULL)
		{
			if($exec->isScope())
			{
				$exec->removeVariableLocal($name);
			}
			
			if($exec->isScopeRoot())
			{
				break;
			}
			
			$exec = $exec->getParentExecution();
		}
	}
	
	/**
	 * Remove the given variable from the current scope.
	 * 
	 * @param string $name
	 */
	public function removeVariableLocal($name)
	{
		if(!$this->isScope())
		{
			return $this->getScope()->removeVariableLocal($name);
		}
		
		unset($this->variables[(string)$name]);
		
		$this->markModified();
	}
	
	/**
	 * Get a map of all variables visible to the execution.
	 * 
	 * @return array<string, mixed>
	 */
	public function getVariables()
	{
		return $this->computeVariables();
	}
	
	/**
	 * Get a map of all variables being set in the scope of the execution.
	 */
	public function getVariablesLocal()
	{
		if($this->isScope())
		{
			return $this->variables;
		}
		
		return $this->getScope()->getVariablesLocal();
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
	 * Get the last transition that has been taken.
	 * 
	 * @return Transition Last transition or NULL if no transition has been taken.
	 */
	public function getTransition()
	{
		return $this->transition;
	}
	
	/**
	 * Set the current / last transition being taken by the execution.
	 * 
	 * @param Transition $transition
	 */
	public function setTransition(Transition $transition = NULL)
	{
		$this->transition = $transition;
		
		$this->markModified();
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
		
		$this->engine->pushCommand($this->engine->createExecuteNodeCommand($this, $node));
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
	 * @param array<string, mixed> $variables Signal data.
	 * @param array<string, mixed> $delegation Signal delegation data.
	 * 
	 * @throws \RuntimeException When the execution is terminated or not within a wait state.
	 */
	public function signal($signal = NULL, array $variables = [], array $delegation = [])
	{
		if($this->isTerminated())
		{
			throw new \RuntimeException(sprintf('Cannot signal terminated %s', $this));
		}
		
		if(!$this->isWaiting())
		{
			throw new \RuntimeException(sprintf('%s is not in a wait state', $this));
		}
		
		$this->engine->pushCommand($this->engine->createSignalExecutionCommand($this, $signal, $variables, $delegation));
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
		
		if($transition !== NULL && !$transition instanceof Transition)
		{
			$transition = $this->model->findTransition($transition);
		}
		
		$this->engine->pushCommand($this->engine->createTakeTransitionCommand($this, $transition));
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
							if($rec !== $this)
							{
								$rec->terminate(false);
							}
						}
					}
					
					foreach($this->findChildExecutions() as $child)
					{
						$this->parentExecution->registerChildExecution($child);
					}
					
					$this->parentExecution->node = $this->node;
					$this->parentExecution->transition = $this->transition;
					$this->parentExecution->setActive(true);
						
					$this->engine->debug('Merged concurrent {execution} into {root}', [
						'execution' => (string)$this,
						'root' => (string)$this->parentExecution
					]);
						
					$this->terminate(false);
					
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
					if($rec !== $this)
					{
						$rec->terminate(false);
					}
				}
					
				if($this->isConcurrent() && 0 == count($this->findConcurrentExecutions()))
				{
					$this->parentExecution->setActive(true);
					
					$this->terminate(false);
			
					return $this->parentExecution->terminate();
				}
				
				$this->terminate();
				
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
						$rec->terminate(false);
						
						$terminated++;
					}
				}
				
				$this->setState(self::STATE_CONCURRENT, false);
				
				$root->setNode($this->node);
				$root->setActive(true);
				
				if($terminated > 0)
				{
					$this->engine->debug('Merged {count} concurrent executions into {execution}', [
						'count' => $terminated,
						'execution' => (string)$root
					]);
				}
				
				$this->markModified(true);
				
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
				$rec->terminate(false);
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
		$root->setState(self::STATE_SCOPE, $this->isScope());
	
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
		$this->variables = [];
	
		$this->markModified();
		
		$this->engine->registerExecution($root);
		
		return $root;
	}
	
	public function markModified($deep = false)
	{
		if($this->syncState !== self::SYNC_STATE_REMOVED)
		{
			$this->syncState = self::SYNC_STATE_MODIFIED;
		}
		
		if($deep)
		{
			foreach($this->childExecutions as $child)
			{
				$child->markModified($deep);
			}
		}
	}
	
	protected function hasState($state)
	{
		return $state === ($this->state & $state);
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
