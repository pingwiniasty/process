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
use KoolKode\Process\Behavior\ExclusiveChoiceBehavior;
use KoolKode\Process\Behavior\SyncBehavior;
use KoolKode\Process\Behavior\WaitStateBehavior;

class ParallelExecutionTest extends ProcessTestCase
{
    public function testDefaultFork()
    {
        $builder = new ProcessBuilder('Default Fork Behavior');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'A');
        $builder->transition('t2', 'start', 'B');
        $builder->transition('t3', 'start', 'C');
        
        $builder->node('A')->behavior(new WaitStateBehavior());
        $builder->node('B')->behavior(new WaitStateBehavior());
        $builder->node('C')->behavior(new WaitStateBehavior());
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isRootExecution());
        $this->assertFalse($process->isTerminated());
        $this->assertFalse($process->isActive());
        $this->assertEquals(3, $this->processEngine->countWaiting($process));
        
        foreach ($this->processEngine->findWaitingExecutions($process) as $execution) {
            $this->assertTrue($execution->isActive());
            $this->assertTrue($execution->isWaiting());
            
            $this->processEngine->signal($execution);
            
            $this->assertTrue($execution->isTerminated());
            $this->assertFalse($execution->isWaiting());
        }
        
        $this->assertEquals(0, $this->processEngine->countConcurrent($process));
        $this->assertEquals(0, $this->processEngine->countWaiting($process));
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isTerminated());
        $this->assertFalse($process->isWaiting());
    }

    public function testSyncGate()
    {
        $builder = new ProcessBuilder('Sync Gate');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'A');
        $builder->transition('t2', 'start', 'B');
        
        $builder->node('A')->behavior(new WaitStateBehavior());
        $builder->transition('t3', 'A', 'gate');
        
        $builder->passNode('B');
        $builder->transition('t4', 'B', 'gate');
        
        $builder->node('gate')->behavior(new SyncBehavior());
        $builder->transition('t5', 'gate', 'C');
        $builder->transition('t6', 'gate', 'D');
        
        $builder2 = new ProcessBuilder('Gate Part 2');
        
        $builder2->node('C')->behavior(new WaitStateBehavior());
        $builder2->transition('t7', 'C', 'end');
        
        $builder2->passNode('D');
        $builder2->transition('t8', 'D', 'end');
        
        $builder2->passNode('end');
        
        $builder->append($builder2);
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        $this->assertEquals(2, $this->processEngine->countConcurrent($process));
        
        $waiting = $this->processEngine->findWaitingExecutions($process)[0];
        $this->assertEquals('A', $waiting->getNode()->getId());
        
        $waiting->signal();
        $this->assertFalse($process->isTerminated());
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        $this->assertEquals(1, $this->processEngine->countConcurrent($process));
        
        $waiting = $this->processEngine->findWaitingExecutions($process)[0];
        $this->assertEquals('C', $waiting->getNode()->getId());
        
        $waiting->signal();
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isTerminated());
    }

    public function square(Execution $execution)
    {
        $execution->setVariable('number', pow($execution->getVariable('number', 2), 2));
    }

    public function testParallelForkWithEnd()
    {
        $builder = new ProcessBuilder('Parallel Fork With End Test');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'receiveOffer');
        
        $builder->node('receiveOffer')->behavior(new CallbackBehavior([
            $this,
            'square'
        ]));
        $builder->transition('t2', 'receiveOffer', 'fork');
        
        $builder->passNode('fork');
        $builder->transition('t3', 'fork', 'specification');
        $builder->transition('t4', 'fork', 'registration');
        
        $builder->node('specification')->behavior(new CallbackBehavior([
            $this,
            'square'
        ]));
        $builder->transition('t5', 'specification', 'end1');
        
        $builder->node('registration')->behavior(new CallbackBehavior([
            $this,
            'square'
        ]));
        $builder->transition('t6', 'registration', 'end2');
        
        $builder->passNode('end1');
        $builder->passNode('end2');
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isTerminated());
        
        foreach ($process->findConcurrentExecutions() as $execution) {
            $this->assertFalse($execution->isActive());
            $this->assertTrue($execution->isTerminated());
        }
        
        $this->assertEquals(256, $process->getVariable('number'));
    }

    public function testParallelForkAndJoin()
    {
        $builder = new ProcessBuilder('Parallel Fork and Join');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'fork');
        
        $builder->passNode('fork');
        $builder->transition('t2', 'fork', 'service');
        $builder->transition('t3', 'fork', 'user');
        
        $builder->passNode('service');
        $builder->transition('t4', 'service', 'join');
        
        $builder->node('user')->behavior(new WaitStateBehavior());
        $builder->transition('t5', 'user', 'join');
        
        $builder->node('join')->behavior(new SyncBehavior());
        $builder->transition('t6', 'join', 'dump');
        
        $counter = 0;
        $builder->node('dump')->behavior(new CallbackBehavior(function (Execution $execution) use (& $counter) {
            $counter++;
        }));
        $builder->transition('t7', 'dump', 'verify');
        
        $builder->node('verify')->behavior(new WaitStateBehavior());
        $builder->transition('t8', 'verify', 'end');
        
        $builder->passNode('end');
        
        $this->assertEmpty($builder->validate());
        $this->assertEquals(0, $counter);
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertFalse($process->isTerminated());
        $this->assertFalse($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertEquals(0, $counter);
        
        $this->processEngine->signalAll($process);
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isWaiting());
        
        $process->signal();
        $this->assertTrue($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertTrue($process->isTerminated());
        $this->assertEquals(1, $counter);
    }

    public function testMultiParallelBehavior()
    {
        $builder = new ProcessBuilder('Multiple Parallel Forks and Joins');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 's1');
        
        $builder->passNode('s1');
        $builder->transition('t2', 's1', 'A');
        $builder->transition('t3', 's1', 'B');
        
        $builder->node('A')->behavior(new WaitStateBehavior());
        $builder->transition('t4', 'A', 's2');
        
        $builder->passNode('B');
        $builder->transition('t5', 'B', 'j1');
        
        $builder->passNode('s2');
        $builder->transition('t6', 's2', 'C');
        $builder->transition('t7', 's2', 'j1');
        
        $builder->node('j1')->behavior(new SyncBehavior());
        $builder->transition('t8', 'j1', 'D');
        
        $builder->passNode('C');
        $builder->transition('t9', 'C', 'j2');
        
        $builder->node('D')->behavior(new WaitStateBehavior());
        $builder->transition('t10', 'D', 'j2');
        
        $builder->node('j2')->behavior(new SyncBehavior());
        $builder->transition('t11', 'j2', 'E');
        
        $builder->passNode('E');
        $builder->transition('t12', 'E', 'end');
        
        $builder->passNode('end');
        
        $process = $this->processEngine->startProcess(unserialize(serialize($builder->build())));
        
        $this->assertEquals(2, $this->processEngine->countConcurrent($process));
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        $waiting = $this->processEngine->findWaitingExecutions($process)[0];
        
        foreach ($this->processEngine->findConcurrentExecutions($process) as $concurrent) {
            if (!$concurrent->isWaiting()) {
                $this->assertEquals('j1', $concurrent->getNode()->getId());
            }
        }
        
        $this->assertFalse($process->isTerminated());
        $this->assertFalse($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertTrue($waiting->isActive());
        $this->assertTrue($waiting->isWaiting());
        $this->assertFalse($waiting->isTerminated());
        
        $waiting->signal();
        $this->assertEquals(2, $this->processEngine->countConcurrent($process));
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        $waiting = $this->processEngine->findWaitingExecutions($process)[0];
        
        foreach ($this->processEngine->findConcurrentExecutions($process) as $concurrent) {
            if (!$concurrent->isWaiting()) {
                $this->assertEquals('j2', $concurrent->getNode()->getId());
            }
        }
        
        $this->assertFalse($process->isTerminated());
        $this->assertFalse($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertTrue($waiting->isActive());
        $this->assertTrue($waiting->isWaiting());
        $this->assertFalse($waiting->isTerminated());
        
        $waiting->signal();
        $this->assertTrue($process->isTerminated());
        $this->assertTrue($process->isActive());
        $this->assertFalse($process->isWaiting());
        
        $this->assertEquals(0, $this->processEngine->countConcurrent($process));
        $this->assertEquals(0, $this->processEngine->countWaiting($process));
    }

    public function testConcurrentExecutionMessageTrigger()
    {
        $builder = new ProcessBuilder('Concurrent Execution Message Trigger');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'A');
        
        $builder->passNode('A');
        $builder->transition('t2', 'A', 'join');
        $builder->transition('t5', 'A', 'message');
        
        $builder->node('message')->behavior(new WaitStateBehavior());
        $builder->transition('t3', 'message', 'join');
        
        $builder->node('join')->behavior(new SyncBehavior());
        $builder->transition('t4', 'join', 'end');
        
        $builder->passNode('end');
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->processEngine->signalAll($process);
        
        $this->assertTrue($process->isTerminated());
    }

    public function testExclusiveParallelMerge()
    {
        $builder = new ProcessBuilder('Merging into a parallel branch using XOR');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'p1');
        
        $builder->passNode('p1');
        $builder->transition('t2', 'p1', 'B');
        $builder->transition('t3', 'p1', 'A');
        
        $builder->passNode('A');
        $builder->transition('t6', 'A', 'x1');
        
        $builder->passNode('B');
        $builder->transition('t4', 'B', 'C');
        
        $builder->node('C')->behavior(new WaitStateBehavior());
        $builder->transition('t5', 'C', 'p2');
        
        $builder->passNode('x1');
        $builder->transition('t7', 'x1', 'p2');
        
        $builder->node('p2')->behavior(new SyncBehavior());
        $builder->transition('t8', 'p2', 'D');
        
        $builder->passNode('D');
        $builder->transition('t9', 'D', 'x2');
        
        $builder->node('x2')->behavior(new ExclusiveChoiceBehavior('t13'));
        $builder->transition('t10', 'x2', 'p3')->trigger(new ExpressionTrigger($this->parseExp('#{ reject }')));
        $builder->transition('t13', 'x2', 'E');
        
        $builder->passNode('p3');
        $builder->transition('t11', 'p3', 'x1');
        $builder->transition('t12', 'p3', 'B');
        
        $builder->passNode('E');
        $builder->transition('t14', 'E', 'end');
        
        $builder->passNode('end');
        
        $process = $this->processEngine->startProcess($builder->build(), [
            'reject' => true
        ]);
        
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        
        $this->processEngine->signalAll($process);
        $process->setVariable('reject', false);
        
        $this->assertEquals(1, $this->processEngine->countWaiting($process));
        
        $this->processEngine->signalAll($process);
        $this->assertTrue($process->isTerminated());
    }
}
