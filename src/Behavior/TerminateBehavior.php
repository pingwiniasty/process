<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Behavior;

use KoolKode\Process\Command\TerminateExecutionCommand;
use KoolKode\Process\Execution;

/**
 * Kills the path of execution by pushing a low priority command to the engine.
 * 
 * @author Martin Schröder
 */
class TerminateBehavior implements BehaviorInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function execute(Execution $execution)
	{
		$execution->getEngine()->pushCommand(new TerminateExecutionCommand($execution));
	}
}
