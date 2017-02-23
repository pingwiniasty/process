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
 * Uses a generic PHP callback as behavior of a node.
 * 
 * @author Martin Schröder
 */
class CallbackBehavior implements BehaviorInterface, \Serializable
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     * 
     * @codeCoverageIgnore
     */
    public function serialize()
    {
        throw new \RuntimeException('Callback behaviors are for testing only and cannot be serialized');
    }

    /**
     * {@inheritdoc}
     * 
     * @codeCoverageIgnore
     */
    public function unserialize($serialized) { }

    /**
     * {@inheritdoc}
     */
    public function execute(Execution $execution)
    {
        $trans = call_user_func($this->callback, $execution);
        
        return $execution->takeAll(($trans === null) ? $trans : (array) $trans, [
            $execution
        ]);
    }
}
