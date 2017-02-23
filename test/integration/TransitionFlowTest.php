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
use KoolKode\Process\Behavior\WaitStateBehavior;

class TransitionFlowTest extends ProcessTestCase
{
    public function provideTransitionTriggers()
    {
        return [
            [false, false, 0],
            [false, true, 7],
            [true, false, 3],
            [true, true, 10]
        ];
    }

    /**
     * @dataProvider provideTransitionTriggers
     */
    public function testForkWithTransitionTriggers($a, $b, $result)
    {
        $builder = new ProcessBuilder('Fork with Transition Triggers');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'A')->trigger(new ExpressionTrigger($this->parseExp('#{ a }')));
        $builder->transition('t2', 'start', 'B')->trigger(new ExpressionTrigger($this->parseExp('#{ b }')));
        
        $builder->node('A')->behavior(new CallbackBehavior(function (Execution $ex) {
            $ex->setVariable('result', $ex->getVariable('result', 0) + 3);
        }));
        
        $builder->node('B')->behavior(new CallbackBehavior(function (Execution $ex) {
            $ex->setVariable('result', $ex->getVariable('result', 0) + 7);
        }));
        
        $process = $this->processEngine->startProcess($builder->build(), [
            'a' => $a,
            'b' => $b
        ]);
        
        $this->assertTrue($process->isTerminated());
        $this->assertEquals($result, $process->getVariable('result', 0));
    }
    
    public function provideTransitionTrigger()
    {
        return [
            [0, false],
            [1, true],
            [-241, true]
        ];
    }

    /**
     * @dataProvider provideTransitionTrigger
     */
    public function testTransitionTriggerBlocksTake($proceed, $done)
    {
        $builder = new ProcessBuilder('Transition Trigger blocks take()');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'A')->trigger(new ExpressionTrigger($this->parseExp('#{ proceed ? true : false }')));
        
        $builder->node('A')->behavior(new CallbackBehavior(function (Execution $execution) {
            $execution->setVariable('done', true);
        }));
        
        $process = $this->processEngine->startProcess($builder->build(), [
            'proceed' => $proceed
        ]);
        
        $this->assertTrue($process->isTerminated());
        $this->assertEquals($done, $process->getVariable('done', false));
    }
    
    public function provideBasicTransitionsAndOutcome()
    {
        return [
            ['t2', 'a'],
            ['t3', 'b'],
        ];
    }

    /**
     * @dataProvider provideBasicTransitionsAndOutcome
     */
    public function testBasicTransitions($transition, $outcome)
    {
        $builder = new ProcessBuilder('Fork Process Based on Signaled Transition ID');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'fork');
        
        $builder->node('fork')->behavior(new WaitStateBehavior());
        $builder->transition('t2', 'fork', 'a');
        $builder->transition('t3', 'fork', 'b');
        
        $behavior = new CallbackBehavior(function (Execution $execution) {
            $execution->setVariable('outcome', $execution->getNode()->getId());
        });
        
        $builder->node('a')->behavior($behavior);
        $builder->node('b')->behavior($behavior);
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isWaiting());
        $this->assertFalse($process->isTerminated());
        
        $process->signal($transition);
        $this->assertTrue($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertTrue($process->isTerminated());
        
        $this->assertEquals($outcome, $process->getVariable('outcome'));
    }
}
