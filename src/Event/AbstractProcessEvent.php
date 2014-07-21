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
 * Base class for any kind of process-related event.
 * 
 * @author Martin Schröder
 */
abstract class AbstractProcessEvent
{
	protected $source;
	protected $execution;
	
	public function __construct(Item $source, Execution $execution)
	{
		$this->source = $source;
		$this->execution = $execution;
	}
	
	public function getSource()
	{
		return $this->source;
	}
	
	public function getExecution()
	{
		return $this->execution;
	}
}
