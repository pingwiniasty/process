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

use KoolKode\Expression\ExpressionAccessInterface;

/**
 * Proxies expression access to an execution and allows for additional virtual
 * scope variables.
 * 
 * @author Martin Schröder
 */
class ExecutionAccess implements ExpressionAccessInterface
{
	protected $execution;
	
	protected $variables = [];
	
	public function __construct(Execution $execution)
	{
		$this->execution = $execution;
	}
	
	public function getExecution()
	{
		return $this->execution;
	}
	
	public function resolveExpressionValue($name, & $isResolved)
	{
		$isResolved = true;
		
		if(array_key_exists($name, $this->variables))
		{
			return $this->variables[$name];
		}
		
		return $this->execution->getVariable($name, NULL);
	}
	
	public function setVariable($name, $value)
	{
		$this->variables[(string)$name] = $value;
	}
}
