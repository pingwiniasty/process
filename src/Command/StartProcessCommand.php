<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Command;

use KoolKode\Process\EngineInterface;
use KoolKode\Process\Event\StartProcessEvent;
use KoolKode\Process\Execution;
use KoolKode\Process\Node;
use KoolKode\Process\ProcessDefinition;
use KoolKode\Util\UUID;

class StartProcessCommand extends AbstractCommand
{
	protected $model;
	
	protected $startNode;
	
	protected $variables;
	
	public function __construct(ProcessDefinition $model, Node $startNode = NULL, array $variables = [])
	{
		$this->model = $model;
		$this->variables = $variables;
		
		if($startNode === NULL)
		{
			$initial = $model->findInitialNodes();
			
			if(count($initial) != 1)
			{
				throw new \RuntimeException(sprintf('Process "%s" does not declare exactly 1 start node', $model->getTitle()));
			}
			
			$this->startNode = array_shift($initial);
		}
		else
		{
			$this->startNode = $startNode;
		}
	}
	
	public function execute(EngineInterface $engine)
	{
		$process = $this->createRootExecution($engine);
		
		foreach($this->variables as $k => $v)
		{
			$process->setVariable($k, $v);
		}
		
		$engine->registerExecution($process);
		$engine->notify(new StartProcessEvent($this->startNode, $process));
		
		$process->execute($this->startNode);
		
		return $process;
	}
	
	protected function createRootExecution(EngineInterface $engine)
	{
		return new Execution(UUID::createRandom(), $engine, $this->model);
	}
}
