<?php

/*
 * This file is part of KoolKode Process.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Process\Event;

/**
 * Is triggered immediately after a process instance has been created, the process start
 * node is being set and initial process variables are populated, the process instance has
 * not executed any kind of behavior yet.
 * 
 * @author Martin Schröder
 */
class StartProcessEvent extends AbstractProcessEvent { }
