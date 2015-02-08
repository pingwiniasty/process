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
use KoolKode\Process\Event\EnterNodeEvent;
use KoolKode\Process\Execution;
use KoolKode\Process\Node;
use KoolKode\Util\UUID;

/**
 * Have an exeution enter a node and execute the behavior.
 * 
 * @author Martin Schröder
 */
class ExecuteNodeCommand extends AbstractCommand
{
	protected $executionId;
	
	protected $nodeId;
	
	public function __construct(Execution $execution, Node $node)
	{
		$this->executionId = (string)$execution->getId();
		$this->nodeId = (string)$node->getId();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function isSerializable()
	{
		return true;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(EngineInterface $engine)
	{
		$execution = $engine->findExecution(new UUID($this->executionId));
		$node = $execution->getProcessModel()->findNode($this->nodeId);
		
		$execution->setTimestamp(microtime(true));
		$execution->setNode($node);
			
		$engine->debug('{execution} entering {node}', [
			'execution' => (string)$execution,
			'node' => (string)$node
		]);
		$engine->notify(new EnterNodeEvent($node, $execution));
			
		$behavior = $node->getBehavior();
			
		if($behavior === NULL)
		{
			$execution->takeAll(NULL, [$execution]);
		}
		else
		{
			$behavior->execute($execution);
		}
	}
}
