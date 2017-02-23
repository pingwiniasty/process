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
 * Serialozable implementation of a command that does nothing.
 * 
 * @codeCoverageIgnore
 * 
 * @author Martin Schröder
 */
class VoidCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    public function isSerializable()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(EngineInterface $engine)
    {
        // Nothing to do here... :)
    }
}
