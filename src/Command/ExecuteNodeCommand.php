<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Command;

use KoolKode\Process\EngineInterface;
use KoolKode\Process\Execution;
use KoolKode\Process\Node;

/**
 * Queue up execution of a specific node, useful when deferring process execution.
 * 
 * @author Martin Schröder
 */
class ExecuteNodeCommand extends AbstractCommand
{
	protected $execution;
	
	protected $node;
	
	public function __construct(Execution $execution, Node $node)
	{
		$this->execution = $execution;
		$this->node = $node;
	}
	
	public function execute(EngineInterface $engine)
	{
		$this->execution->execute($this->node);
	}
}
