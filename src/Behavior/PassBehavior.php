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
 * Pass-through behavior that simply takes all outgoing transitions.
 * 
 * @author Martin Schröder
 */
class PassBehavior implements BehaviorInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(Execution $execution)
    {
        $execution->takeAll();
    }
}
