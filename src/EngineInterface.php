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
use KoolKode\Process\Command\CommandInterface;
use KoolKode\Util\UUID;
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
	 * Queue up a command to be executed by the process engine in the outer execution, that is
	 * it will not be executed by the current call to executeCommand().
	 *
	 * @param CommandInterface $command
	 */
	public function pushDeferredCommand(CommandInterface $command);
	
	/**
	 * Have the process engine execute the given command and return the result.
	 * 
	 * The engine will execute all pushed commands with higher priority before the target command. The target command
	 * will be executed in it's own isolated scope.
	 * 
	 * @param CommandInterface $command
	 * @return mixed
	 */
	public function executeCommand(CommandInterface $command);
	
	/**
	 * Get an execution by ID.
	 * 
	 * @param UUID $id
	 * @return Execution
	 */
	public function findExecution(UUID $id);
	
	/**
	 * Register an execution with the process engine, very helpful in implementing persistence.
	 * 
	 * @param Execution $execution
	 */
	public function registerExecution(Execution $execution);
	
	/**
	 * Create a command that will execute a node in the context of the given execution.
	 * 
	 * @param Execution $execution
	 * @param Node $node
	 * @return CommandInterface
	 */
	public function createExecuteNodeCommand(Execution $execution, Node $node);
	
	/**
	 * Create a command that will signal an execution passing a signal and optional data.
	 * 
	 * @param Execution $execution
	 * @param string $signal
	 * @param array $variables
	 * @return CommandInterface
	 */
	public function createSignalExecutionCommand(Execution $execution, $signal = NULL, array $variables = []);
}
