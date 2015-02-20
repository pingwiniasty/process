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

use KoolKode\Process\Behavior\SignalableBehaviorInterface;
use KoolKode\Process\EngineInterface;
use KoolKode\Process\Event\SignalNodeEvent;
use KoolKode\Process\Execution;
use KoolKode\Process\Node;
use KoolKode\Util\UUID;

/**
 * Signal an execution using a signal name and optional variables.
 * 
 * @author Martin Schröder
 */
class SignalExecutionCommand extends AbstractCommand
{
	/**
	 * Execution ID.
	 * 
	 * @var string
	 */
	protected $executionId;
	
	/**
	 * Signal name.
	 * 
	 * @var string
	 */
	protected $signal;
	
	/**
	 * Serialized signal data.
	 * 
	 * @var string
	 */
	protected $variables;
	
	/**
	 * Serialized signal delegation data.
	 * 
	 * @var string
	 */
	protected $delegation;
	
	/**
	 * Wake the given execution up using the given signal / variables.
	 * 
	 * @param Execution $execution
	 * @param string $signal
	 * @param array $variables
	 * @param array $delegation
	 */
	public function __construct(Execution $execution, $signal = NULL, array $variables = [], array $delegation = [])
	{
		$this->executionId = (string)$execution->getId();
		$this->signal = ($signal === NULL) ? NULL : (string)$signal;
		$this->variables = serialize($variables);
		$this->delegation = serialize($delegation);
	}
	
	/**
	 * {@inheritdoc}
	 * 
	 * @codeCoverageIgnore
	 */
	public function isSerializable()
	{
		return true;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getPriority()
	{
		return self::PRIORITY_DEFAULT + 50;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(EngineInterface $engine)
	{
		$execution = $engine->findExecution(new UUID($this->executionId));
		$node = $execution->getNode();
		
		$vars = unserialize($this->variables);
		$delegation = unserialize($this->delegation);
		
		$execution->wakeUp();
		
		$engine->debug('Signaling <{signal}> to {execution}', [
			'signal' => ($this->signal === NULL) ? 'NULL' : $this->signal,
			'execution' => (string)$execution
		]);
		$engine->notify(new SignalNodeEvent($node, $execution, $this->signal, $vars, $delegation));
		
		$this->singalExecution($node, $execution, $vars, $delegation);
	}
	
	/**
	 * Perform logic to actually signal the execution.
	 * 
	 * @param Node $node
	 * @param Execution $execution
	 * @param array $vars
	 * @param array $delegation
	 */
	protected function singalExecution(Node $node, Execution $execution, array $vars, array $delegation)
	{
		$behavior = $node->getBehavior();
		
		if($behavior instanceof SignalableBehaviorInterface)
		{
			$behavior->signal($execution, $this->signal, $vars, $delegation);
		}
	}
}
