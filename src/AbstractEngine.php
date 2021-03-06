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

use KoolKode\Event\EventDispatcherInterface;
use KoolKode\Expression\ExpressionContextFactoryInterface;
use KoolKode\Process\Command\CommandInterface;
use KoolKode\Process\Command\ExecuteNodeCommand;
use KoolKode\Process\Command\SignalExecutionCommand;
use KoolKode\Process\Command\TakeTransitionCommand;
use KoolKode\Util\UUID;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Implements the basics of a process engine.
 * 
 * It is highly recommended to extend this class instead of implementing the EngineInterface.
 * 
 * @author Martin Schröder
 */
abstract class AbstractEngine implements EngineInterface
{
    use LoggerTrait;

    /**
     * Determines the execution depth, defaults to 0, will be managed by performExecution().
     * 
     * @var integer
     */
    protected $executionDepth = 0;

    /**
     * Counts the number of commands that have been executed during the current execution.
     * 
     * @var integer
     */
    protected $executionCount = 0;

    /**
     * Active executions being tracked by the process engine.
     * 
     * @var array
     */
    protected $executions = [];

    /**
     * Holds the prioritized command queue being used.
     * 
     * @var array<CommandInterface>
     */
    protected $commands = [];

    /**
     * Keeps track of command results for later retrieval.
     * 
     * @var \SplObjectStorage
     */
    protected $commandResults;

    /**
     * Delegate logger being used by the engine, can be null!
     * 
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Event dispatcher being used to deliver process-related events.
     * 
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Expression context factory being used with expressions related to a process context.
     * 
     * @var ExpressionContextFactoryInterface
     */
    protected $expressionContextFactory;

    public function __construct(EventDispatcherInterface $dispatcher, ExpressionContextFactoryInterface $factory)
    {
        $this->eventDispatcher = $dispatcher;
        $this->expressionContextFactory = $factory;
        
        $this->commandResults = new \SplObjectStorage();
    }

    /**
     * Inject or remove the target logger instance.
     * 
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     * 
     * @codeCoverageIgnore
     */
    public function log($level, $message, array $context = null)
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function notify($event)
    {
        $this->eventDispatcher->notify($event);
    }

    /**
     * {@inheritdoc}
     */
    public function getExpressionContextFactory()
    {
        return $this->expressionContextFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function findExecution(UUID $id)
    {
        $ref = (string) $id;
        
        if (isset($this->executions[$ref])) {
            return $this->executions[$ref];
        }
        
        throw new \OutOfBoundsException(sprintf('Execution not found: %s', $ref));
    }

    /**
     * {@inheritdoc}
     */
    public function registerExecution(Execution $execution)
    {
        if (empty($this->executions[(string) $execution->getId()])) {
            $this->syncNewExecution($execution, $execution->collectSyncData());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pushCommand(CommandInterface $command)
    {
        $this->storeCommand($command);
        
        if ($this->executionDepth == 0) {
            try {
                // Execute remaining commands when this is a top level execution.
                $this->performExecution(function () {
                    
                    while (!empty($this->commands)) {
                        $cmd = array_shift($this->commands);
                        
                        $this->commandResults[$cmd] = $cmd->execute($this);
                        $this->executionCount++;
                    }
                });
            } finally {
                // Free command result memory after top level execution.
                $this->commandResults = new \SplObjectStorage();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeCommand(CommandInterface $command)
    {
        $this->storeCommand($command);
        
        $this->performExecution(function () use ($command) {
            
            $priority = $command->getPriority();
            
            while (!empty($this->commands) && $this->commands[0]->getPriority() >= $priority) {
                $cmd = array_shift($this->commands);
                
                $this->commandResults[$cmd] = $cmd->execute($this);
                $this->executionCount++;
                
                if ($cmd === $command) {
                    // Bail out as soon as target command has been executed.
                    break;
                }
            }
            
            if ($this->executionDepth == 1) {
                // Execute remaining commands if this is a top level execution.
                while (!empty($this->commands)) {
                    $cmd = array_shift($this->commands);
                    
                    $this->commandResults[$cmd] = $cmd->execute($this);
                    $this->executionCount++;
                }
            }
        });
        
        try {
            if ($this->commandResults->offsetExists($command)) {
                return $this->commandResults[$command];
            }
        } finally {
            if ($this->executionDepth == 0) {
                // Free command result memory after top level execution.
                $this->commandResults = new \SplObjectStorage();
            }
        }
    }

    /**
     * Queues a command according to it's priority.
     * 
     * @param CommandInterface $command
     * @return CommandInterface
     */
    protected function storeCommand(CommandInterface $command)
    {
        $priority = $command->getPriority();
        
        for ($count = count($this->commands), $i = 0; $i < $count; $i++) {
            if ($this->commands[$i]->getPriority() < $priority) {
                array_splice($this->commands, $i, 0, [
                    $command
                ]);
                
                return $command;
            }
        }
        
        return $this->commands[] = $command;
    }

    /**
     * Triggers the given callback, can be extended in order to wrap interceptors around execution of commands.
     * 
     * Be sure to call the parent method when you override this method!
     * 
     * Keep in mind that nested executions are allowed and need to be dealt with when implementing
     * features such as transaction handling.
     * 
     * @param callable $callback This callback triggers the actual execution of commands!
     * @return mixed Result of an execution, not relevant when only pushing commands.
     */
    protected function performExecution(callable $callback)
    {
        $this->executionDepth++;
        
        $count = $this->executionCount;
        $this->executionCount = 0;
        
        $this->debug('BEGIN execution (depth: {depth})', [
            'depth' => $this->executionDepth
        ]);
        
        try {
            $result = $callback();
        } finally {
            $this->debug('END execution (depth: {depth}), {count} commands executed', [
                'depth' => $this->executionDepth,
                'count' => $this->executionCount
            ]);
            
            $this->executionCount = $count;
            $this->executionDepth--;
        }
        
        $this->syncExecutions();
        
        return $result;
    }

    public function syncExecutions()
    {
        $modified = [];
        $removed = [];
        
        foreach (array_values($this->executions) as $execution) {
            switch ($execution->getSyncState()) {
                case Execution::SYNC_STATE_MODIFIED:
                    $modified[] = $execution;
                    break;
                case Execution::SYNC_STATE_REMOVED:
                    $removed[] = $execution;
                    break;
            }
            
            $execution->setSyncState(Execution::SYNC_STATE_NO_CHANGE);
        }
        
        foreach ($modified as $execution) {
            $this->syncModifiedExecution($execution, $execution->collectSyncData());
        }
        
        foreach ($removed as $execution) {
            $this->syncRemovedExecution($execution);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createExecuteNodeCommand(Execution $execution, Node $node)
    {
        return new ExecuteNodeCommand($execution, $node);
    }

    /**
     * {@inheritdoc}
     */
    public function createSignalExecutionCommand(Execution $execution, $signal = null, array $variables = [], array $delegation = [])
    {
        return new SignalExecutionCommand($execution, $signal, $variables, $delegation);
    }

    /**
     * {@inheritdoc}
     */
    public function createTakeTransitionCommand(Execution $execution, Transition $transition = null)
    {
        return new TakeTransitionCommand($execution, $transition);
    }

    protected function syncNewExecution(Execution $execution, array $syncData)
    {
        $execution->setSyncData($syncData);
        $execution->setSyncState(Execution::SYNC_STATE_NO_CHANGE);
        
        $this->executions[(string) $execution->getId()] = $execution;
        
        $this->debug('Sync created {execution}', [
            'execution' => (string) $execution
        ]);
    }

    protected function syncModifiedExecution(Execution $execution, array $syncData)
    {
        $execution->setSyncData($syncData);
        $execution->setSyncState(Execution::SYNC_STATE_NO_CHANGE);
        
        $this->debug('Sync modified {execution}', [
            'execution' => (string) $execution
        ]);
    }

    protected function syncRemovedExecution(Execution $execution)
    {
        unset($this->executions[(string) $execution->getId()]);
        
        $this->debug('Sync removed {execution}', [
            'execution' => (string) $execution
        ]);
    }
}
