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
	 * Signal data.
	 * 
	 * @var array
	 */
	protected $variables;
	
	/**
	 * Wake the given execution up using the given signal / variables.
	 * 
	 * @param Execution $execution
	 * @param string $signal
	 * @param array $variables
	 */
	public function __construct(Execution $execution, $signal = NULL, array $variables = [])
	{
		$this->executionId = (string)$execution->getId();
		$this->signal = ($signal === NULL) ? NULL : (string)$signal;
		$this->variables = $variables;
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
		$node = $execution->getNode();
		
		$execution->wakeUp();
		
		$engine->debug('Signaling <{signal}> to {execution}', [
			'signal' => ($this->signal === NULL) ? 'NULL' : $this->signal,
			'execution' => (string)$execution
		]);
		$engine->notify(new SignalNodeEvent($node, $execution, $this->signal, $this->variables));
			
		$behavior = $node->getBehavior();
			
		if($behavior instanceof SignalableBehaviorInterface)
		{
			$behavior->signal($execution, $this->signal, $this->variables);
		}
		else
		{
			$execution->takeAll(NULL, [$execution]);
		}
	}
}
