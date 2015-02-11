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
	/**
	 * Execution ID.
	 * 
	 * @var UUID
	 */
	protected $executionId;
	
	/**
	 * Node ID (unique within the process model).
	 * 
	 * @var string
	 */
	protected $nodeId;
	
	/**
	 * Have the given execution execute execute the node's behavior.
	 * 
	 * @param Execution $execution
	 * @param Node $node
	 */
	public function __construct(Execution $execution, Node $node)
	{
		$this->executionId = $execution->getId();
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
		$execution = $engine->findExecution($this->executionId);
		$node = $execution->getProcessModel()->findNode($this->nodeId);
		
		$execution->setTimestamp(microtime(true));
		$execution->setNode($node);
			
		$engine->debug('{execution} entering {node}', [
			'execution' => (string)$execution,
			'node' => (string)$node
		]);
		$engine->notify(new EnterNodeEvent($node, $execution));
		
		$this->executeNode($node, $execution);
	}
	
	/**
	 * Perform logic needed to actually execute the node's behavior.
	 * 
	 * @param Node $node
	 * @param Execution $execution
	 */
	protected function executeNode(Node $node, Execution $execution)
	{
		$node->getBehavior()->execute($execution);
	}
}
