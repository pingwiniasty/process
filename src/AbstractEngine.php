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

abstract class AbstractEngine implements EngineInterface
{
	use LoggerTrait;
	
	protected $commands = [];
	
	protected $logger;
	protected $eventDispatcher;
	
	protected $expressionContextFactory;
	
	public function __construct(EventDispatcherInterface $dispatcher, ExpressionContextFactoryInterface $factory, LoggerInterface $logger = NULL)
	{
		$this->eventDispatcher = $dispatcher;
		$this->expressionContextFactory = $factory;
		$this->logger = $logger;
	}
	
	public function log($level, $message, array $context = NULL)
	{
		if($this->logger !== NULL)
		{
			$this->logger->log($level, $message, $context);
		}
	}
	
	public function notify($event)
	{
		$this->eventDispatcher->notify($event);
	}
	
	public function getExpressionContextFactory()
	{
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
