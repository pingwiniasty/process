<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Behavior;

use KoolKode\Process\Execution;

/**
 * Turns a node into an exclusive fork / join with an optional default transition.
 * 
 * @author Martin Schröder
 */
class ExclusiveChoiceBehavior implements BehaviorInterface
{
	protected $defaultTransition;
	
	public function __construct($defaultTransition = NULL)
	{
		$this->defaultTransition = ($defaultTransition === NULL) ? NULL : (string)$defaultTransition;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute(Execution $execution)
	{
		foreach($execution->getProcessModel()->findOutgoingTransitions($execution->getNode()->getId()) as $trans)
		{
			if($trans->getId() === $this->defaultTransition)
			{
				continue;
			}
			
			if($trans->isEnabled($execution))
			{
				return $execution->take($trans);
			}
		}
		
		if($this->defaultTransition !== NULL)
		{
			return $execution->take($this->defaultTransition);
		}
		
		$message = sprintf(
			'Execution %s about to get stuck in exclusive choice within node "%s"',
			$execution->getId(),
			$execution->getNode()->getId()
		);
		
		throw new StuckException($message);
	}
}
