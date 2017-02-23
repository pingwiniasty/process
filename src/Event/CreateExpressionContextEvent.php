<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Event;

use KoolKode\Process\ExecutionAccess;

/**
 * Is triggered whenever an expression context is created around an execution.
 * 
 * @author Martin Schröder
 */
class CreateExpressionContextEvent
{
    public $access;

    public function __construct(ExecutionAccess $access)
    {
        $this->access = $access;
    }
}
