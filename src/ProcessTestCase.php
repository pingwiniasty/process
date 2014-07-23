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

use KoolKode\Event\EventDispatcher;
use KoolKode\Expression\Parser\ExpressionLexer;
use KoolKode\Expression\Parser\ExpressionParser;
use KoolKode\Expression\ExpressionContextFactory;
use KoolKode\Expression\ExpressionInterface;

/**
 * Base class for a unit tests with process engine support.
 * 
 * @author Martin Schröder
 */
abstract class ProcessTestCase extends \PHPUnit_Framework_TestCase
{
	protected $eventDispatcher;
	
	protected $expressionParser;
	
	protected $processEngine;
	
	protected function setUp()
	{
		parent::setUp();
		
		$logger = NULL;
	
		$lexer = new ExpressionLexer();
		$lexer->setDelimiters('#{', '}');
	
		$this->expressionParser = new ExpressionParser($lexer);
		$this->eventDispatcher = new EventDispatcher($logger);
	
		$factory = new ExpressionContextFactory();
		$factory->getResolvers()->registerResolver(new ExecutionExpressionResolver());
	
		$this->processEngine = new TestEngine($this->eventDispatcher, $factory);
	}
	
	/**
	 * Parse the given string for an expression.
	 * 
	 * @param string $input
	 * @return ExpressionInterface
	 */
	protected function parseExp($input)
	{
		return $this->expressionParser->parse($input);
	}
}
