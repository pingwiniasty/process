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

use KoolKode\Process\Behavior\CallbackBehavior;
use KoolKode\Process\Behavior\SyncBehavior;
use KoolKode\Process\Behavior\WaitStateBehavior;
use KoolKode\Process\Command\CallbackCommand;
use KoolKode\Process\Command\CommandInterface;

class SignalExecutionTest extends ProcessTestCase
{
    public function testSignalExample()
    {
        $builder = new ProcessBuilder('Signal Throw / Catch Example');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'split');
        
        $builder->passNode('split');
        $builder->transition('t2', 'split', 'task');
        $builder->transition('t3', 'split', 'catch');
        
        $builder->node('task')->behavior(new WaitStateBehavior());
        $builder->transition('t4', 'task', 'throw');
        
        $builder->node('throw')->behavior(new CallbackBehavior(function (Execution $execution) {
            
            $catch = $execution->getProcessModel()->findNode('catch');
            $tmp = $execution->findConcurrentExecutions($catch);
            $this->assertCount(1, $tmp);
            
            foreach ($tmp as $concurrent) {
                $this->processEngine->pushCommand(new CallbackCommand(function () use ($concurrent) {
                    $concurrent->signal();
                }, CommandInterface::PRIORITY_DEFAULT + 500));
            }
        }));
        $builder->transition('t5', 'throw', 'join');
        
        $builder->node('catch')->behavior(new WaitStateBehavior());
        $builder->transition('t6', 'catch', 'join');
        
        $builder->node('join')->behavior(new SyncBehavior());
        $builder->transition('t7', 'join', 'end');
        
        $builder->passNode('end');
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $concurrent = $this->processEngine->findConcurrentExecutions($process);
        $this->assertCount(2, $concurrent);
        
        foreach ($concurrent as $execution) {
            if ($execution->getNode()->getId() == 'task') {
                $execution->signal();
            }
        }
        
        $this->assertTrue($process->isTerminated());
    }
}
