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

/**
 * Base class for a command with default priority.
 * 
 * @author Martin Schröder
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return self::PRIORITY_DEFAULT;
    }
}
