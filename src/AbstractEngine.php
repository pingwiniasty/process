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

use KoolKode\Context\ContainerInterface;
use KoolKode\Event\EventDispatcher;
use KoolKode\Event\EventDispatcherInterface;
use KoolKode\Expression\ExpressionContextFactory;
use KoolKode\Expression\ExpressionContextFactoryInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractEngine implements EngineInterface
{
	protected $commands = [];
	
	protected $container;
	protected $logger;
	protected $eventDispatcher;
	protected $expressionContextFactory;
	
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}
	
	public function getContainer()
	{
		return $this->container;
	}
	
	public function error($message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
			$this->logger->error($message, $context);
		}
	}
	
	public function warning($message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
			$this->logger->warning($message, $context);
		}
	}
	
	public function info($message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
			$this->logger->info($message, $context);
		}
	}
	
	public function debug($message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
			$this->logger->debug($message, $context);
		}
	}
	
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}
	
	public function notify($event)
	{
		if($this->eventDispatcher !== NULL)
		{
			$this->eventDispatcher->notify($event);
		}
	}
	
	public function setEventDispatcher(EventDispatcherInterface $dispatcher)
	{
		$this->eventDispatcher = $dispatcher;
	}
	
	public function getExpressionContextFactory()
	{
		if($this->expressionContextFactory === NULL)
		{
			return new ExpressionContextFactory();
		}
		
		return $this->expressionContextFactory;
	}
	
	public function setExpressionContextFactory(ExpressionContextFactoryInterface $factory)
	{
		$this->expressionContextFactory = $factory;
	}
	
	public function pushCommand(CommandInterface $command)
	{
		$this->commands[] = $command;
	}
	
	public function executeNextCommand()
	{
		if(empty($this->commands))
		{
			return NULL;
		}
		
		$command = array_shift($this->commands);
		$command->execute($this);
		
		return $command;
	}
}
