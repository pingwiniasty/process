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

use KoolKode\Expression\ExpressionInterface;

class ExpressionTrigger implements TriggerInterface
{
	protected $expression;
	
	public function __construct(ExpressionInterface $expression)
	{
		$this->expression = $expression;
	}
	
	public function serialize()
	{
		return serialize($this->expression);
	}
	
	public function unserialize($serialized)
	{
		$this->expression = unserialize($serialized);
	}
	
	public function getExpression()
	{
		return $this->expression;
	}
	
	public function isEnabled(Execution $execution)
	{
		$result = call_user_func($this->expression, $execution->getExpressionContext()) ? true : false;
		
// 		printf(">> EXP \"%s\" = %s\n", $this->expression, var_export($result, true));
		
		return $result;
	}
}
