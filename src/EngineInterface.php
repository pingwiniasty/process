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

use Psr\Log\LoggerInterface;

interface EngineInterface extends LoggerInterface
{
	public function notify($event);
	
	public function getExpressionContextFactory();
	
	public function pushCommand(CommandInterface $command);
	
	public function executeNextCommand();
	
	public function startProcess(ProcessDefinition $definition, array $variables = [], callable $factory = NULL);
	
	public function registerExecution(Execution $execution);
}
