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

use KoolKode\Expression\Resolver\ExpressionResolverInterface;

/**
 * Hooks into the expression context and provides access to execution variables.
 * 
 * @author Martin Schröder
 */
class ExecutionExpressionResolver implements ExpressionResolverInterface
{
	public function getPriority()
	{
		return self::PRIORITY_DEFAULT + 100;
	}
	
	public function canResolve($subject)
	{
		return $subject instanceof Execution;
	}
	
	public function resolveExpressionValue($subject, $name, & $isResolved)
	{
		if($subject->hasVariable($name))
		{
			$isResolved = true;
				
			return $subject->getVariable($name);
		}
	}
}
