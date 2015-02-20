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

/**
 * Is thrown when an execution gets stuck at a choice behavior.
 * 
 * @author Martin Schröder
 */
class StuckException extends \RuntimeException { }
