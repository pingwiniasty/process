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

class ProcessModelTest extends \PHPUnit_Framework_TestCase
{
    public function testCanAccessIdAndTitle()
    {
        $title = 'Foo Title';
        $id = UUID::createRandom();
        
        $model = new ProcessModel([], $title, $id);
        
        $this->assertEquals($id, $model->getId());
        $this->assertEquals($title, $model->getTitle());
        $this->assertEquals([], $model->getItems());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDetectsNodeWithoutBehaviore()
    {
        $builder = new ProcessBuilder();
        $builder->node('foo');
        
        $builder->build();
    }

    public function testAccessNodesAndTransitions()
    {
        $builder = new ProcessBuilder();
        $s1 = $builder->startNode('s1');
        $t1 = $builder->transition('t1', 's1', 'n1');
        $t2 = $builder->transition('t2', 's1', 'n2');
        $n1 = $builder->passNode('n1');
        $n2 = $builder->passNode('n2');
        
        $model = $builder->build();
        $this->assertEquals([
            $s1
        ], $model->findStartNodes());
        
        $this->assertEquals([
            $s1,
            $n1,
            $n2
        ], $model->findNodes());
        
        $this->assertEquals([
            $t1,
            $t2
        ], $model->findTransitions());
        
        $this->assertEquals([
            $t1,
            $t2
        ], $model->findOutgoingTransitions($s1));
        
        $this->assertEquals([
            $t2
        ], $model->findIncomingTransitions($n2));
        
        $this->assertSame($n2, $model->findItem('n2'));
        $this->assertSame($t1, $model->findItem($t1));
        $this->assertSame($n1, $model->findNode($n1));
        $this->assertSame($t1, $model->findTransition($t1));
    }

    public function provideMissingItemCalls()
    {
        yield ['findItem', 'N/A'];
        yield ['findNode', 'N/A'];
        yield ['findTransition', 'N/A'];
    }

    /**
     * @dataProvider provideMissingItemCalls
     * @expectedException \OutOfBoundsException
     */
    public function testWillThrowExceptionWhenItemsAreMissing($methodName, $arg)
    {
        call_user_func([
            (new ProcessBuilder())->build(),
            $methodName
        ], $arg);
    }
}
