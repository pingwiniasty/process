<?php

/*
 * This file is part of KoolKode Process.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Process\Event;

use KoolKode\Process\Execution;
use KoolKode\Process\Item;

/**
 * Is triggered before sending a signal to a signalable behavior of a node.
 * 
 * @author Martin Schröder
 */
class SignalNodeEvent extends AbstractProcessEvent
{
	public $signal;
	
	public $variables;
	
	public function __construct(Item $source, Execution $execution, $signal = NULL, array $variables = [])
	{
		parent::__construct($source, $execution);
		
		$this->signal = $signal;
		$this->variables = $variables;
	}
}
