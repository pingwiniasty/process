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
use KoolKode\Process\Behavior\InclusiveChoiceBehavior;
use KoolKode\Process\Behavior\WaitStateBehavior;

class ForkInclusiveTest extends ProcessTestCase
{
    public function provideInclusiveTestData()
    {
        return [
            [3, 3, 'B'],
            [2, 2, 'B'],
            [34, 3, 'A'],
            [1, -2, 'A'],
            [2, 3, 'C'],
            [-23, 3, 'C']
        ];
    }

    /**
     * @dataProvider provideInclusiveTestData
     */
    public function testInclusiveChoice($num, $threshold, $result)
    {
        $builder = new ProcessBuilder('Inclusive Choice Fork Test');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'input');
        
        $builder->node('input')->behavior(new WaitStateBehavior());
        $builder->transition('t2', 'input', 'gate');
        
        $builder->node('gate')->behavior(new InclusiveChoiceBehavior('t5'));
        $builder->transition('t3', 'gate', 'A')->trigger(new ExpressionTrigger($this->parseExp('#{num > threshold}')));
        $builder->transition('t4', 'gate', 'B')->trigger(new ExpressionTrigger($this->parseExp('#{num == threshold}')));
        $builder->transition('t5', 'gate', 'C');
        
        $callback = new CallbackBehavior(function (Execution $execution) {
            $execution->setVariable('result', $execution->getNode()->getId());
        });
        
        $builder->node('A')->behavior($callback);
        $builder->node('B')->behavior($callback);
        $builder->node('C')->behavior($callback);
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isWaiting());
        $process->setVariable('num', $num);
        $process->setVariable('threshold', $threshold);
        $process->signal();
        $this->assertTrue($process->isTerminated());
        $this->assertEquals($result, $process->getVariable('result'));
    }

    public function testInclusiveForkAndJoin()
    {
        $builder = new ProcessBuilder('Inclusive Fork and Join Test');
        
        $builder->startNode('start');
        $builder->transition('t0', 'start', 'X');
        
        $builder->node('X')->behavior(new WaitStateBehavior());
        $builder->transition('t1', 'X', 'fork');
        
        $builder->node('fork')->behavior(new InclusiveChoiceBehavior('t2'));
        $builder->transition('t2', 'fork', 'A');
        $builder->transition('t3', 'fork', 'B')->trigger(new ExpressionTrigger($this->parseExp('#{ num > 5 }')));
        $builder->transition('t4', 'fork', 'C')->trigger(new ExpressionTrigger($this->parseExp('#{ num > 10 }')));
        
        $builder->node('A')->behavior(new WaitStateBehavior());
        $builder->transition('t5', 'A', 'join');
        
        $builder->node('B')->behavior(new WaitStateBehavior());
        $builder->transition('t6', 'B', 'join');
        
        $builder->node('C')->behavior(new WaitStateBehavior());
        $builder->transition('t7', 'C', 'join');
        
        $builder->node('join')->behavior(new InclusiveChoiceBehavior());
        $builder->transition('t8', 'join', 'end');
        
        $builder->passNode('end');
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isWaiting());
        $process->setVariable('num', 7);
        
        $process->signal();
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        
        $this->processEngine->signalAll($process);
        $this->assertTrue($process->isTerminated());
    }

    public function testJoinParallel()
    {
        $builder = new ProcessBuilder();
        
        $builder->startNode('s1');
        $builder->transition('t1', 's1', 'j1');
        $builder->transition('t2', 's1', 'w1');
        $builder->transition('tx', 's1', 'wx');
        
        // Additional wait state that is not reachable by the inclusive choice node.
        $builder->node('wx')->behavior(new WaitStateBehavior());
        
        $builder->node('w1')->behavior(new WaitStateBehavior());
        $builder->transition('t3', 'w1', 'j1');
        
        $builder->node('j1')->behavior(new InclusiveChoiceBehavior());
        $builder->transition('t4', 'j1', 'w2');
        
        $builder->node('w2')->behavior(new WaitStateBehavior());
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertEquals(2, $this->processEngine->countWaiting($process));
        
        $this->processEngine->signal($this->processEngine->findWaitingExecutions($process)[0]);
        $this->assertEquals(2, $this->processEngine->countWaiting($process));
        
        foreach ($this->processEngine->findWaitingExecutions($process) as $exec) {
            if ($exec->getNode()->getId() === 'wx') {
                $this->processEngine->signal($exec);
            }
        }
        
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        
        foreach ($this->processEngine->findWaitingExecutions($process) as $exec) {
            $this->assertEquals('w2', $exec->getNode()->getId());
        }
        
        $this->processEngine->signal($exec);
        
        $this->assertEquals(0, $this->processEngine->countWaiting($process));
    }

    /**
     * @expectedException \KoolKode\Process\Behavior\StuckException
     */
    public function testGetStuck()
    {
        $builder = new ProcessBuilder();
        
        $builder->startNode('s');
        $builder->transition('t1', 's', 'c');
        
        $builder->node('c')->behavior(new InclusiveChoiceBehavior());
        $builder->transition('t2', 'c', 'e')->trigger(new ExpressionTrigger($this->parseExp('#{ false }')));
        
        $builder->passNode('e');
        
        $this->processEngine->startProcess($builder->build());
    }
}
