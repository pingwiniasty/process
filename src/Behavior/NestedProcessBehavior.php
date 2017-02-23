<?php

/*
 * This file is part of KoolKode Process.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Process\Behavior;

use KoolKode\Process\Execution;
use KoolKode\Process\ProcessModel;

/**
 * Allows for nested execution of another process from a parent process.
 * 
 * @author Martin Schröder
 */
class NestedProcessBehavior implements SignalableBehaviorInterface
{
    protected $process;

    protected $isolateScope;

    protected $inputs;

    protected $outputs;

    public function __construct(ProcessModel $process, $isolateScope = true, array $inputs = [], array $outputs = [])
    {
        $this->process = $process;
        $this->isolateScope = $isolateScope ? true : false;
        $this->inputs = $inputs;
        $this->outputs = $outputs;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Execution $execution)
    {
        $nodes = $this->process->findInitialNodes();
        
        if (count($nodes) !== 1) {
            throw new \RuntimeException(sprintf('No single start node found in process "%s"', $this->process->getTitle()));
        }
        
        $startNode = array_shift($nodes);
        
        $sub = $execution->createNestedExecution($this->process, $startNode, true, $this->isolateScope);
        
        foreach ($this->inputs as $target => $source) {
            if ($execution->hasVariable($source)) {
                $sub->setVariable($target, $execution->getVariable($source));
            }
        }
        
        $execution->waitForSignal();
        
        $sub->execute($startNode);
    }

    public function signal(Execution $execution, $signal, array $variables = [], array $delegation = [])
    {
        $sub = $execution->getEngine()->findExecution($delegation['executionId']);
        
        if (!$sub instanceof Execution) {
            throw new \RuntimeException('Missing reference to nested execution');
        }
        
        foreach ($this->outputs as $target => $source) {
            if ($sub->hasVariable($source)) {
                $execution->setVariable($target, $sub->getVariable($source));
            }
        }
        
        $execution->takeAll(null, [
            $execution
        ]);
    }
}
