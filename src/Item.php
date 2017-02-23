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

/**
 * Items are the basic unit of process model elements.
 * 
 * @author Martin Schröder
 */
abstract class Item
{
    protected $id;

    public function __construct($id)
    {
        $this->id = (string) $id;
    }

    public function getId()
    {
        return $this->id;
    }
}
