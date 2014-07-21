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

/**
 * Enhances an activity by providing a mechanism to signal additional behavior.
 * 
 * @author Martin Schröder
 */
interface SignalableActivityInterface extends ActivityInterface
{
	/**
	 * React to the given signal and payload within the context of the given execution.
	 * 
	 * @param Execution $execution
	 * @param string $signal Name of the signal or NULL when no name is given.
	 * @param array<string, mixed> $variables
	 */
	public function signal(Execution $execution, $signal, array $variables = []);
}
