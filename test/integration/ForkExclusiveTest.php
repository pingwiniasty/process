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
use KoolKode\Process\Behavior\WaitStateBehavior;

class ForkExclusiveTest extends ProcessTestCase
{
    public function provideXorTestData()
    {
        return [
            [120, 120],
            [220, 190],
            [340, 310]
        ];
    }

    /**
     * @dataProvider provideXorTestData
     */
    public function testExclusiveChoiceForkAndJoin($amount, $expected)
    {
        $builder = new ProcessBuilder('Exclusive Fork and Join Test');
        
        $builder->startNode('start');
        $builder->transition('t1', 'start', 'amount');
        
        $builder->node('amount')->behavior(new WaitStateBehavior());
        $builder->transition('t2', 'amount', 'choice');
        
        $builder->node('choice')->behavior(new ExclusiveChoiceBehavior('t4'));
        $builder->transition('t3', 'choice', 'discount')->trigger(new ExpressionTrigger($this->parseExp('#{ amount >= 200 }')));
        $builder->transition('t4', 'choice', 'join');
        
        $builder->node('discount')->behavior(new CallbackBehavior(function (Execution $execution) {
            $execution->setVariable('discount', 30);
        }));
        $builder->transition('t5', 'discount', 'join');
        
        $builder->passNode('join');
        $builder->transition('t6', 'join', 'bill');
        
        $sum = 0;
        
        $builder->node('bill')->behavior(new CallbackBehavior(function (Execution $execution) use (& $sum) {
            $sum = (int) $execution->getVariable('amount') - (int) $execution->getVariable('discount', 0);
        }));
        $builder->transition('t7', 'bill', 'end');
        
        $builder->passNode('end');
        
        $this->assertEmpty($builder->validate());
        
        $process = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($process->isActive());
        $this->assertTrue($process->isWaiting());
        $this->assertFalse($process->isTerminated());
        $this->assertEquals(0, $sum);
        
        $process->setVariable('amount', $amount);
        $process->signal();
        
        $this->assertTrue($process->isActive());
        $this->assertFalse($process->isWaiting());
        $this->assertTrue($process->isTerminated());
        $this->assertEquals($expected, $sum);
    }

    /**
     * @expectedException \KoolKode\Process\Behavior\StuckException
     */
    public function testGetStuck()
    {
        $builder = new ProcessBuilder();
        
        $builder->startNode('s');
        $builder->transition('t1', 's', 'c');
        
        $builder->node('c')->behavior(new ExclusiveChoiceBehavior());
        $builder->transition('t2', 'c', 'e')->trigger(new ExpressionTrigger($this->parseExp('#{ false }')));
        
        $builder->passNode('e');
        
        $this->processEngine->startProcess($builder->build());
    }
}
