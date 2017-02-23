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

/**
 * Implements a transition trigger backed by an expression.
 * 
 * @author Martin Schröder
 */
class ExpressionTrigger implements TriggerInterface
{
    protected $expression;

    public function __construct(ExpressionInterface $expression)
    {
        $this->expression = $expression;
    }

    public function isEnabled(Execution $execution)
    {
        return call_user_func($this->expression, $execution->getExpressionContext()) ? true : false;
    }
}
