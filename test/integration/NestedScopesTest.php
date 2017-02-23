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

use KoolKode\Process\Behavior\NestedProcessBehavior;
use KoolKode\Process\Behavior\WaitStateBehavior;

class NestedScopesTest extends ProcessTestCase
{
    public function provideScopeIsolationFlag()
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @dataProvider provideScopeIsolationFlag
     */
    public function testNestedScopeExecution($isolate)
    {
        $sub = new ProcessBuilder('Sub Process');
        
        $sub->startNode('s2');
        $sub->transition('t3', 's2', 'B');
        
        $sub->node('B')->behavior(new WaitStateBehavior());
        $sub->transition('t4', 'B', 'e2');
        
        $sub->passNode('e2');
        
        $builder = new ProcessBuilder('Nested Scope Execution');
        
        $builder->startNode('s1');
        $builder->transition('t1', 's1', 'A');
        
        $builder->node('A')->behavior(new WaitStateBehavior());
        $builder->transition('t2', 'A', 'sub');
        
        $builder->node('sub')->behavior(new NestedProcessBehavior($sub->build(), $isolate, [
            'tmp' => 'subject'
        ], [
            'subject' => 'tmp'
        ]));
        $builder->transition('t5', 'sub', 'e1');
        
        $builder->passNode('e1');
        
        $process = $this->processEngine->startProcess($builder->build());
        $process->signal(null, [
            'subject' => 'hello'
        ]);
        
        $executions = $process->findChildExecutions();
        $this->assertCount(1, $executions);
        
        $nested = array_shift($executions);
        $this->assertTrue($nested instanceof Execution);
        $this->assertEquals('B', $nested->getNode()->getId());
        $this->assertEquals('t3', $nested->getTransition()->getId());
        
        $this->assertEquals('hello', $process->getVariable('subject'));
        $this->assertEquals('hello', $nested->getVariable('tmp'));
        
        $nested->signal(null, [
            'tmp' => 'world'
        ]);
        $this->assertTrue($nested->isTerminated());
        $this->assertTrue($process->isTerminated());
        
        $this->assertEquals('world', $nested->getVariable('tmp'));
        $this->assertEquals('world', $process->getVariable('subject'));
        
        if ($isolate) {
            $this->assertFalse($nested->hasVariableLocal('subject'));
            $this->assertTrue($nested->hasVariableLocal('tmp'));
            $this->assertTrue($process->hasVariableLocal('subject'));
            $this->assertFalse($process->hasVariableLocal('tmp'));
        } else {
            $this->assertFalse($nested->hasVariableLocal('subject'));
            $this->assertFalse($nested->hasVariableLocal('tmp'));
            $this->assertTrue($process->hasVariableLocal('subject'));
            $this->assertTrue($process->hasVariableLocal('tmp'));
        }
    }
}
