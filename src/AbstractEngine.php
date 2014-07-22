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
	 * Holds the prioritized command queue being used.
	 * 
	 * @var array<CommandInterface>
	 */
	protected $commands = [];
	
	/**
	 * Delegate logger being used by the engine, can be NULL!
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
	}
	
	/**
	 * Inject or remove the target logger instance.
	 * 
	 * @param LoggerInterface $logger
	 */
	public function setLogger(LoggerInterface $logger = NULL)
	{
		$this->logger = $logger;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function log($level, $message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
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
	public function pushCommand(CommandInterface $command)
	{
		$this->storeCommand($command);
		
		if($this->executionDepth == 0)
		{
			$this->performExecution(function() {
				
				$count = 0;
				
				while(!empty($this->commands))
				{
					$cmd = array_shift($this->commands);
					$cmd->execute($this);
					
					$count++;
				}
				
				$this->debug(sprintf('Performed %u consecutive commands', $count));
			});
		}
	}
	
	/**
	 * Get the current depth of execution.
	 * 
	 * @return integer
	 */
	public function getExecutionDepth()
	{
		return $this->executionDepth;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function executeCommand(CommandInterface $command)
	{
		$this->storeCommand($command);
		
		while(!empty($this->commands))
		{
			$cmd = array_shift($this->commands);
			
			if($cmd === $command)
			{
				break;
			}
			
			$cmd->execute($this);
		}
		
		$tmp = $this->commands;
		$this->commands = [];
			
		try
		{
			$result = $this->performExecution(function() use($command) {
				
				$count = 1;
				$result = $command->execute($this);
				
				while(!empty($this->commands))
				{
					$cmd = array_shift($this->commands);
					$cmd->execute($this);
					
					$count++;
				}
				
				$this->debug(sprintf('Performed %u consecutive commands', $count));
				
				return $result;
			});
		}
		finally
		{
			$this->commands = $tmp;	
		}
			
		return $result;
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
		
		for($count = count($this->commands), $i = 0; $i < $count; $i++)
		{
			if($this->commands[$i]->getPriority() < $priority)
			{
				array_splice($this->commands, $i, 0, [$command]);
			
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
		
		try
		{
			return $callback();
		}
		finally
		{
			$this->executionDepth--;
		}
	}
}
