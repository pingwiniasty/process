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

use KoolKode\Expression\ExpressionContextFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a contract for a process engine.
 * 
 * @author Martin Schröder
 */
interface EngineInterface extends LoggerInterface
{
	/**
	 * Notify listeners of a process event.
	 * 
	 * @param object $event
	 */
	public function notify($event);
	
	/**
	 * Get access to the expression context factory being used by the process engine.
	 * 
	 * @return ExpressionContextFactoryInterface
	 */
	public function getExpressionContextFactory();
	
	/**
	 * Queue up a command to be executed by the process engine.
	 * 
	 * @param CommandInterface $command
	 */
	public function pushCommand(CommandInterface $command);
	
	/**
	 * Have the process engine execute the next queued up command.
	 * 
	 * @return CommandInterface The executed command or NULL if there are no more commands left.
	 */
	public function executeNextCommand();
	
	public function startProcess(ProcessDefinition $definition, array $variables = [], callable $factory = NULL);
	
	public function registerExecution(Execution $execution);
}
