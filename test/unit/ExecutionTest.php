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

use KoolKode\Util\UUID;

class ExecutionTest extends ProcessTestCase
{
    protected function createDummyExecution()
    {
        $builder = new ProcessBuilder();
        $builder->startNode('start');
        
        return new Execution(UUID::createRandom(), $this->getMock(EngineInterface::class), $builder->build());
    }

    public function testkeepsTrackOfEngine()
    {
        $builder = new ProcessBuilder();
        $builder->startNode('start');
        
        $engine = $this->getMock(EngineInterface::class);
        $execution = new Execution(UUID::createRandom(), $engine, $builder->build());
        
        $this->assertSame($engine, $execution->getEngine());
    }

    public function testSyncData()
    {
        $execution = $this->createDummyExecution();
        $data = [
            'foo' => 'bar'
        ];
        
        $this->assertEquals([], $execution->getSyncData());
        
        $execution->setSyncData($data);
        $this->assertEquals($data, $execution->getSyncData());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDetectsInvalidSyncState()
    {
        $this->createDummyExecution()->setSyncState(18264);
    }

    public function provideBlockedMethods()
    {
        yield ['execute', [new Node('foo')]];
        yield ['waitForSignal'];
        yield ['wakeUp'];
        yield ['signal'];
        yield ['take'];
        yield ['takeAll'];
    }

    /**
     * @dataProvider provideBlockedMethods
     * @expectedException \RuntimeException
     */
    public function testTerminationBlocksMethodCalls($methodName, array $args = [])
    {
        $builder = new ProcessBuilder();
        $builder->startNode('start');
        
        $execution = $this->processEngine->startProcess($builder->build());
        
        $this->assertTrue($execution instanceof Execution);
        $this->assertTrue($execution->isTerminated());
        
        call_user_func_array([
            $execution,
            $methodName
        ], $args);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCannotSignalExecutionNotInWaitState()
    {
        $this->createDummyExecution()->signal();
    }

    public function testMarkModified()
    {
        $exec = $this->createDummyExecution();
        $this->assertEquals(Execution::SYNC_STATE_NO_CHANGE, $exec->getSyncState());
        
        $exec->markModified();
        $this->assertEquals(Execution::SYNC_STATE_MODIFIED, $exec->getSyncState());
    }

    public function testMarkModifiedDeep()
    {
        $exec = $this->createDummyExecution();
        $child = $exec->createExecution();
        
        $exec->setSyncState(Execution::SYNC_STATE_NO_CHANGE);
        $child->setSyncState(Execution::SYNC_STATE_NO_CHANGE);
        $this->assertEquals(Execution::SYNC_STATE_NO_CHANGE, $exec->getSyncState());
        $this->assertEquals(Execution::SYNC_STATE_NO_CHANGE, $child->getSyncState());
        
        $exec->markModified(true);
        $this->assertEquals(Execution::SYNC_STATE_MODIFIED, $exec->getSyncState());
        $this->assertEquals(Execution::SYNC_STATE_MODIFIED, $child->getSyncState());
    }

    public function testVarScopeLocal()
    {
        $exec = $this->createDummyExecution();
        $child = $exec->createExecution();
        
        $this->assertFalse($child->hasVariable('foo'));
        
        $child->setVariableLocal('foo', 'bar');
        $this->assertTrue($exec->hasVariableLocal('foo'));
        $this->assertTrue($child->hasVariableLocal('foo'));
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $exec->getVariablesLocal());
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $child->getVariablesLocal());
        
        $child->setVariableLocal('baz', 'bum');
        $child->setVariableLocal('foo', null);
        $this->assertTrue($exec->hasVariableLocal('baz'));
        $this->assertFalse($exec->hasVariableLocal('foo'));
        $this->assertTrue($child->hasVariableLocal('baz'));
        $this->assertFalse($child->hasVariableLocal('foo'));
        
        $child->removeVariableLocal('baz');
        $this->assertEquals([], $exec->getVariablesLocal());
        $this->assertEquals([], $child->getVariablesLocal());
    }

    public function testVarScopeConcurrent()
    {
        $exec = $this->createDummyExecution();
        $child = $exec->createExecution();
        
        $this->assertFalse($child->hasVariable('foo'));
        
        $child->setVariable('foo', 'bar');
        $this->assertTrue($exec->hasVariable('foo'));
        $this->assertTrue($exec->hasVariableLocal('foo'));
        $this->assertTrue($child->hasVariable('foo'));
        $this->assertTrue($child->hasVariableLocal('foo'));
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $exec->getVariables());
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $exec->getVariablesLocal());
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $child->getVariables());
        
        $this->assertEquals([
            'foo' => 'bar'
        ], $child->getVariablesLocal());
        
        $child->setVariable('foo', null);
        $this->assertFalse($exec->hasVariable('foo'));
        $this->assertFalse($exec->hasVariableLocal('foo'));
        $this->assertFalse($child->hasVariable('foo'));
        $this->assertFalse($child->hasVariableLocal('foo'));
    }

    public function testAccessExistingVariable()
    {
        $exec = $this->createDummyExecution();
        $exec->setVariable('foo', 'bar');
        
        $this->assertEquals('bar', $exec->getVariable('foo'));
    }

    public function testAccessVariableUsingDefaultValue()
    {
        $this->assertEquals('bar', $this->createDummyExecution()->getVariable('foo', 'bar'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testAccessVariableThrowsException()
    {
        $this->createDummyExecution()->getVariable('foo');
    }

    public function testAccessExistingVariableLocal()
    {
        $exec = $this->createDummyExecution();
        $exec->setVariableLocal('foo', 'bar');
        
        $this->assertEquals('bar', $exec->getVariableLocal('foo'));
    }

    public function testAccessVariableLocalUsingDefaultValue()
    {
        $this->assertEquals('bar', $this->createDummyExecution()->getVariableLocal('foo', 'bar'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testAccessVariableLocalThrowsException()
    {
        $this->createDummyExecution()->getVariableLocal('foo');
    }

    public function testRootExecutionHasNoConcurrentExecutions()
    {
        $exec = $this->createDummyExecution();
        
        $this->assertEquals([], $exec->findConcurrentExecutions());
        $this->assertEquals([], $exec->findInactiveConcurrentExecutions());
    }
}
