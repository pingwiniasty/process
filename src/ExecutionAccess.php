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

use KoolKode\Expression\ExpressionAccessInterface;
use KoolKode\Expression\InspectedValue;

/**
 * Proxies expression access to an execution and allows for additional virtual variables.
 * 
 * @author Martin Schröder
 */
class ExecutionAccess implements ExpressionAccessInterface
{
    protected $execution;

    protected $variables = [];

    public function __construct(Execution $execution)
    {
        $this->execution = $execution;
    }

    public function getExecution()
    {
        return $this->execution;
    }

    public function resolveExpressionValue(InspectedValue $inspection)
    {
        if (array_key_exists($inspection->name, $this->variables)) {
            $inspection->value = $this->variables[$inspection->name];
            
            return true;
        }
        
        if ($this->execution->hasVariable($inspection->name)) {
            $inspection->value = $this->execution->getVariable($inspection->name);
            
            return true;
        }
        
        return false;
    }

    public function setVariable($name, $value)
    {
        $this->variables[(string) $name] = $value;
    }
}
