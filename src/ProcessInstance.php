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

use KoolKode\Process\Event\EndProcessEvent;
use KoolKode\Util\UUID;

/**
 * A process is top-level execution.
 * 
 * @author Martin Schröder
 */
class ProcessInstance extends Execution
{
	public function __construct(UUID $id, EngineInterface $engine, ProcessDefinition $processDefinition)
	{
		$this->id = $id;
		$this->engine = $engine;
		$this->processDefinition = $processDefinition;
		$this->state = self::STATE_SCOPE | self::STATE_ACTIVE;
		
		$engine->debug('Starting process {0}', [$processDefinition->getTitle()]);
		$engine->debug('Created {0}', [(string)$this]);
	}
	
	public function __toString()
	{
		return sprintf('process(%s)', $this->id);
	}
	
	public function terminate()
	{
		parent::terminate();
		
		$this->engine->notify(new EndProcessEvent($this->node, $this));
	}
}
