<?php

namespace KoolKode\Process;

use KoolKode\Event\EventDispatcher;
use KoolKode\Expression\ExpressionContextFactory;
use KoolKode\Expression\Parser\ExpressionLexer;
use KoolKode\Expression\Parser\ExpressionParser;
use KoolKode\Process\Behavior\CallbackBehavior;
use KoolKode\Process\Behavior\ExclusiveChoiceBehavior;
use KoolKode\Process\Behavior\InclusiveChoiceBehavior;
use KoolKode\Process\Behavior\SyncBehavior;
use KoolKode\Process\Behavior\WaitStateBehavior;

class NodeTest extends \PHPUnit_Framework_TestCase
{
	protected $expressionParser;
	
	protected $engine;
	
	protected function setUp()
	{
		parent::setUp();
		
		$lexer = new ExpressionLexer();
		$lexer->setDelimiters('#{', '}');
		
		$this->expressionParser = new ExpressionParser($lexer);
		
		$logger = NULL;
		
		$dispatcher = new EventDispatcher($logger);
		
		$factory = new ExpressionContextFactory();
		$factory->getResolvers()->registerResolver(new ExecutionExpressionResolver());
		
		$this->engine = new TestEngine($dispatcher, $factory, $logger);
	}
	
	protected function exp($input)
	{
		return $this->expressionParser->parse($input);
	}
	
	public function testDefaultFork()
	{
		$builder = new ProcessBuilder('Default Fork Behavior');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'A');
		$builder->transition('t2', 'start', 'B');
		$builder->transition('t3', 'start', 'C');
		
		$builder->node('A')->behavior(new WaitStateBehavior());
		$builder->node('B')->behavior(new WaitStateBehavior());
		$builder->node('C')->behavior(new WaitStateBehavior());
		
		$process = $this->engine->startProcess($builder->build());

		$this->assertFalse($process->isTerminated());
		$this->assertFalse($process->isActive());
		$this->assertEquals(3, $this->engine->countWaiting($process));
		
		foreach($this->engine->findWaitingExecutions($process) as $execution)
		{
			$this->assertTrue($execution->isActive());
			$this->assertTrue($execution->isWaiting());
			
			$this->engine->signal($execution);
			
			$this->assertTrue($execution->isTerminated());
			$this->assertFalse($execution->isWaiting());
		}
		
		$this->assertEquals(0, $this->engine->countConcurrent($process));
		$this->assertEquals(0, $this->engine->countWaiting($process));
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isTerminated());
		$this->assertFalse($process->isWaiting());
	}
	
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
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'A')
				->trigger(new ExpressionTrigger($this->exp('#{ a }')));
		$builder->transition('t2', 'start', 'B')
				->trigger(new ExpressionTrigger($this->exp('#{ b }')));
		
		$builder->node('A')->behavior(new CallbackBehavior(function(Execution $ex) {
			$ex->setVariable('result', $ex->getVariable('result', 0) + 3);
		}));
		
		$builder->node('B')->behavior(new CallbackBehavior(function(Execution $ex) {
			$ex->setVariable('result', $ex->getVariable('result', 0) + 7);
		}));
		
		$process = $this->engine->startProcess($builder->build(), [
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
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'A')
				->trigger(new ExpressionTrigger($this->exp('#{ proceed ? true : false }')));
		
		$builder->node('A')->behavior(new CallbackBehavior(function(Execution $execution) {
			$execution->setVariable('done', true);
		}));
		
		$process = $this->engine->startProcess($builder->build(), [
			'proceed' => $proceed
		]);
		
		$this->assertTrue($process->isTerminated());
		$this->assertEquals($done, $process->getVariable('done', false));
	}
	
	public function testConvergingGate()
	{
		$builder = new ProcessBuilder('Converging Gate');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'A');
		$builder->transition('t2', 'start', 'B');
		
		$builder->node('A')->behavior(new WaitStateBehavior());
		$builder->transition('t3', 'A', 'gate');
		
		$builder->node('B');
		$builder->transition('t4', 'B', 'gate');
		
		$builder->node('gate')->behavior(new SyncBehavior());
		$builder->transition('t5', 'gate', 'C');
		$builder->transition('t6', 'gate', 'D');
		
		$builder->node('C')->behavior(new WaitStateBehavior());
		$builder->transition('t7', 'C', 'end');
		
		$builder->node('D');
		$builder->transition('t8', 'D', 'end');
		
		$builder->node('end');
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertEquals(1, $this->engine->countWaiting($process));
		$this->assertEquals(2, $this->engine->countConcurrent($process));
		
		$waiting = $this->engine->findWaitingExecutions($process)[0];
		$this->assertEquals('A', $waiting->getNode()->getId());
		
		$waiting->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertFalse($process->isTerminated());
		$this->assertEquals(1, $this->engine->countWaiting($process));
		$this->assertEquals(1, $this->engine->countConcurrent($process));
		
		$waiting = $this->engine->findWaitingExecutions($process)[0];
		$this->assertEquals('C', $waiting->getNode()->getId());
		
		$waiting->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isTerminated());
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
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'fork');
		
		$builder->node('fork')->behavior(new WaitStateBehavior());
		$builder->transition('t2', 'fork', 'a');
		$builder->transition('t3', 'fork', 'b');
		
		$behavior = new CallbackBehavior(function(Execution $execution) {
			$execution->setVariable('outcome', $execution->getNode()->getId());
		});
		
		$builder->node('a')->behavior($behavior);
		$builder->node('b')->behavior($behavior);
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isWaiting());
		$this->assertFalse($process->isTerminated());
		
		$process->signal($transition);
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isActive());
		$this->assertFalse($process->isWaiting());
		$this->assertTrue($process->isTerminated());
		
		$this->assertEquals($outcome, $process->getVariable('outcome'));
	}
	
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
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'amount');
		
		$builder->node('amount')->behavior(new WaitStateBehavior());
		$builder->transition('t2', 'amount', 'choice');
		
		$builder->node('choice')->behavior(new ExclusiveChoiceBehavior('t4'));
		$builder->transition('t3', 'choice', 'discount')
				->trigger(new ExpressionTrigger($this->exp('#{ amount >= 200 }')));
		$builder->transition('t4', 'choice', 'join');
		
		$builder->node('discount')->behavior(new CallbackBehavior(function(Execution $execution) {
			$execution->setVariable('discount', 30);
		}));
		$builder->transition('t5', 'discount', 'join');
		
		$builder->node('join');
		$builder->transition('t6', 'join', 'bill');
		
		$sum = 0;
		$builder->node('bill')->behavior(new CallbackBehavior(function(Execution $execution) use(& $sum) {
			$sum = (int)$execution->getVariable('amount') - (int)$execution->getVariable('discount', 0);
		}));
		$builder->transition('t7', 'bill', 'end');
		
		$builder->node('end');
		
		$this->assertEmpty($builder->validate());
	
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isWaiting());
		$this->assertFalse($process->isTerminated());
		$this->assertEquals(0, $sum);
		
		$process->setVariable('amount', $amount);
		$process->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isActive());
		$this->assertFalse($process->isWaiting());
		$this->assertTrue($process->isTerminated());
		$this->assertEquals($expected, $sum);
	}
	
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
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'input');
		
		$builder->node('input')->behavior(new WaitStateBehavior());
		$builder->transition('t2', 'input', 'gate');
		
		$builder->node('gate')->behavior(new InclusiveChoiceBehavior('t5'));
		$builder->transition('t3', 'gate', 'A')
				->trigger(new ExpressionTrigger($this->exp('#{num > threshold}')));
		$builder->transition('t4', 'gate', 'B')
				->trigger(new ExpressionTrigger($this->exp('#{num == threshold}')));
		$builder->transition('t5', 'gate', 'C');
		
		$callback = new CallbackBehavior(function(Execution $execution) {
			$execution->setVariable('result', $execution->getNode()->getId());
		});
		
		$builder->node('A')->behavior($callback);
		$builder->node('B')->behavior($callback);
		$builder->node('C')->behavior($callback);
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertTrue($process->isWaiting());
		$process->setVariable('num', $num);
		$process->setVariable('threshold', $threshold);
		$process->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isTerminated());
		$this->assertEquals($result, $process->getVariable('result'));
	}
	
	public function testInclusiveForkAndJoin()
	{
		$builder = new ProcessBuilder('Inclusive Fork and Join Test');
		
		$builder->node('start')->initial();
		$builder->transition('t0', 'start', 'X');
		
		$builder->node('X')->behavior(new WaitStateBehavior());
		$builder->transition('t1', 'X', 'fork');
		
		$builder->node('fork')->behavior(new InclusiveChoiceBehavior('t2'));
		$builder->transition('t2', 'fork', 'A');
		$builder->transition('t3', 'fork', 'B')
				->trigger(new ExpressionTrigger($this->exp('#{ num > 5 }')));
		$builder->transition('t4', 'fork', 'C')
				->trigger(new ExpressionTrigger($this->exp('#{ num > 10 }')));
		
		$builder->node('A')->behavior(new WaitStateBehavior());
		$builder->transition('t5', 'A', 'join');
		
		$builder->node('B')->behavior(new WaitStateBehavior());
		$builder->transition('t6', 'B', 'join');
		
		$builder->node('C')->behavior(new WaitStateBehavior());
		$builder->transition('t7', 'C', 'join');
		
		$builder->node('join')->behavior(new InclusiveChoiceBehavior());
		$builder->transition('t8', 'join', 'end');
		
		$builder->node('end');
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertTrue($process->isWaiting());
		$process->setVariable('num', 7);
		
		$process->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertEquals(1, $this->engine->countWaiting($process));
		
		$this->engine->signalAll($process);
		$this->assertTrue($process->isTerminated());
	}
	
	public function square(Execution $execution)
	{
		$execution->setVariable('number', pow($execution->getVariable('number', 2), 2));
	}
	
	public function testParallelForkWithEnd()
	{
		$builder = new ProcessBuilder('Parallel Fork With End Test');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'receiveOffer');
		
		$builder->node('receiveOffer')->behavior(new CallbackBehavior([$this, 'square']));
		$builder->transition('t2', 'receiveOffer', 'fork');
		
		$builder->node('fork');
		$builder->transition('t3', 'fork', 'specification');
		$builder->transition('t4', 'fork', 'registration');
		
		$builder->node('specification')->behavior(new CallbackBehavior([$this, 'square']));
		$builder->transition('t5', 'specification', 'end1');
		
		$builder->node('registration')->behavior(new CallbackBehavior([$this, 'square']));
		$builder->transition('t6', 'registration', 'end2');
		
		$builder->node('end1');
		$builder->node('end2');
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isTerminated());
		
		foreach($process->findConcurrentExecutions() as $execution)
		{
			$this->assertFalse($execution->isActive());
			$this->assertTrue($execution->isTerminated());
		}
		
		$this->assertEquals(256, $process->getVariable('number'));
	}
	
	public function testParallelForkAndJoin()
	{
		$builder = new ProcessBuilder('Parallel Fork and Join');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'fork');
		
		$builder->node('fork');
		$builder->transition('t2', 'fork', 'service');
		$builder->transition('t3', 'fork', 'user');
		
		$builder->node('service');
		$builder->transition('t4', 'service', 'join');
			
		$builder->node('user')->behavior(new WaitStateBehavior());
		$builder->transition('t5', 'user', 'join');
		
		$builder->node('join')->behavior(new SyncBehavior());
		$builder->transition('t6', 'join', 'dump');
		
		$counter = 0;
		$builder->node('dump')->behavior(new CallbackBehavior(function(Execution $execution) use(& $counter) {
			$counter++;
		}));
		$builder->transition('t7', 'dump', 'verify');
		
		$builder->node('verify')->behavior(new WaitStateBehavior());
		$builder->transition('t8', 'verify', 'end');
		
		$builder->node('end');
		
		$this->assertEmpty($builder->validate());
		$this->assertEquals(0, $counter);
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->assertFalse($process->isTerminated());
		$this->assertFalse($process->isActive());
		$this->assertFalse($process->isWaiting());
		$this->assertEquals(0, $counter);
		
		$this->engine->signalAll($process);
		$this->assertTrue($process->isActive());
		$this->assertTrue($process->isWaiting());
		
		$process->signal();
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isActive());
		$this->assertFalse($process->isWaiting());
		$this->assertTrue($process->isTerminated());
		$this->assertEquals(1, $counter);
	}
	
	public function testMultiParallelBehavior()
	{
		$builder = new ProcessBuilder('Multiple Parallel Forks and Joins');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 's1');
		
		$builder->node('s1');
		$builder->transition('t2', 's1', 'A');
		$builder->transition('t3', 's1', 'B');
		
		$builder->node('A')->behavior(new WaitStateBehavior());
		$builder->transition('t4', 'A', 's2');
		
		$builder->node('B');
		$builder->transition('t5', 'B', 'j1');
		
		$builder->node('s2');
		$builder->transition('t6', 's2', 'C');
		$builder->transition('t7', 's2', 'j1');
		
		$builder->node('j1')->behavior(new SyncBehavior());
		$builder->transition('t8', 'j1', 'D');
		
		$builder->node('C');
		$builder->transition('t9', 'C', 'j2');
		
		$builder->node('D')->behavior(new WaitStateBehavior());
		$builder->transition('t10', 'D', 'j2');
		
		$builder->node('j2')->behavior(new SyncBehavior());
		$builder->transition('t11', 'j2', 'E');
		
		$builder->node('E');
		$builder->transition('t12', 'E', 'end');
		
		$builder->node('end');
		
		$process = $this->engine->startProcess(unserialize(serialize($builder->build())));
		
		$this->assertEquals(2, $this->engine->countConcurrent($process));
		$this->assertEquals(1, $this->engine->countWaiting($process));
		$waiting = $this->engine->findWaitingExecutions($process)[0];
		
		foreach($this->engine->findConcurrentExecutions($process) as $concurrent)
		{
			if(!$concurrent->isWaiting())
			{
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
		while($this->engine->executeNextCommand());
		
		$this->assertEquals(2, $this->engine->countConcurrent($process));
		$this->assertEquals(1, $this->engine->countWaiting($process));
		$waiting = $this->engine->findWaitingExecutions($process)[0];
		
		foreach($this->engine->findConcurrentExecutions($process) as $concurrent)
		{
			if(!$concurrent->isWaiting())
			{
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
		while($this->engine->executeNextCommand());
		
		$this->assertTrue($process->isTerminated());
		$this->assertTrue($process->isActive());
		$this->assertFalse($process->isWaiting());
		
		$this->assertEquals(0, $this->engine->countConcurrent($process));
		$this->assertEquals(0, $this->engine->countWaiting($process));
	}
	
	public function testExclusiveParallelMerge()
	{
		$builder = new ProcessBuilder('Merging into a parallel branch using XOR');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'p1');
		
		$builder->node('p1');
		$builder->transition('t2', 'p1', 'B');
		$builder->transition('t3', 'p1', 'A');
		
		$builder->node('A');
		$builder->transition('t6', 'A', 'x1');
		
		$builder->node('B');
		$builder->transition('t4', 'B', 'C');
		
		$builder->node('C')->behavior(new WaitStateBehavior());
		$builder->transition('t5', 'C', 'p2');
		
		$builder->node('x1');
		$builder->transition('t7', 'x1', 'p2');
		
		$builder->node('p2')->behavior(new SyncBehavior());
		$builder->transition('t8', 'p2', 'D');
		
		$builder->node('D');
		$builder->transition('t9', 'D', 'x2');
		
		$builder->node('x2')->behavior(new ExclusiveChoiceBehavior('t13'));
		$builder->transition('t10', 'x2', 'p3')
				->trigger(new ExpressionTrigger($this->exp('#{ reject }')));
		$builder->transition('t13', 'x2', 'E');
		
		$builder->node('p3');
		$builder->transition('t11', 'p3', 'x1');
		$builder->transition('t12', 'p3', 'B');
		
		$builder->node('E');
		$builder->transition('t14', 'E', 'end');
		
		$builder->node('end');
		
		$process = $this->engine->startProcess($builder->build(), [
			'reject' => true
		]);
		
		$this->assertEquals(1, $this->engine->countWaiting($process));
		
		$this->engine->signalAll($process);
		$process->removeVariable('reject');
		
		$this->assertEquals(1, $this->engine->countWaiting($process));
		
		$this->engine->signalAll($process);
		$this->assertTrue($process->isTerminated());
	}
	
	public function testConcurrentExecutionMessageTrigger()
	{
		$builder = new ProcessBuilder('Concurrent Execution Message Trigger');
		
		$builder->node('start')->initial();
		$builder->transition('t1', 'start', 'A');
		
		$builder->node('A');
		$builder->transition('t2', 'A', 'join');
		$builder->transition('t5', 'A', 'message');
		
		$builder->node('message')->behavior(new WaitStateBehavior());
		$builder->transition('t3', 'message', 'join');
		
		$builder->node('join')->behavior(new SyncBehavior());
		$builder->transition('t4', 'join', 'end');
		
		$builder->node('end');
		
		$process = $this->engine->startProcess($builder->build());
		
		$this->engine->signalAll($process);
		
		$this->assertTrue($process->isTerminated());
	}
}
