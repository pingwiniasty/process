<?php

/*
 * This file is part of KoolKode Process.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

// FIXME: Remove getters and coupling... needs some work and working unit tests!

namespace KoolKode\Process;

interface EngineInterface
{
	public function getContainer();
	
	public function notify($event);
	
	public function error($message, array $context = NULL);
	
	public function warning($message, array $context = NULL);
	
	public function info($message, array $context = NULL);
	
	public function debug($message, array $context = NULL);
	
	public function getExpressionContextFactory();
	
	public function pushCommand(CommandInterface $command);
	
	public function executeNextCommand();
	
	public function startProcess(ProcessDefinition $definition, array $variables = [], callable $factory = NULL);
	
	public function registerExecution(Execution $execution);
}
