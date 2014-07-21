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
 * A trigger can be attached to a transition turning it into a guarded transition.
 * 
 * @author Martin Schröder
 */
interface TriggerInterface extends \Serializable
{
	/**
	 * Check if the guarded transition is active in the context of the
	 * given execution.
	 * 
	 * @param Execution $execution
	 * @return boolean
	 */
	public function isEnabled(Execution $execution);
}
