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
 * An activity is used to model the behavior of a node within a process.
 * 
 * @author Martin Schröder
 */
interface ActivityInterface
{
	/**
	 * Execute the connected behavior in the context of the given execution.
	 * 
	 * @param Execution $execution
	 */
	public function execute(Execution $execution);
}
