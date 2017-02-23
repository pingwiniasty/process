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

/**
 * Commands encapsulate operations being performed by a process engine.
 * 
 * @author Martin Schröder
 */
interface CommandInterface
{
    /**
     * Default command priority being used.
     * 
     * @var integer
     */
    const PRIORITY_DEFAULT = 1000;

    /**
     * Check if the command can be serialized (allows for storing commands and executing them at a later time).
     * 
     * @return boolean
     */
    public function isSerializable();

    /**
     * Get the command priority.
     * 
     * @return integer
     */
    public function getPriority();

    /**
     * Execute the command in the context of the given process engine.
     * 
     * @param EngineInterface $engine
     */
    public function execute(EngineInterface $engine);
}
