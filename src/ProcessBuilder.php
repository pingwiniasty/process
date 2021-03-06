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

use KoolKode\Process\Behavior\PassBehavior;

/**
 * The process builder is a convenient ultility for programmatic construction of process models.
 * 
 * @author Martin Schröder
 */
class ProcessBuilder implements \Countable, \IteratorAggregate
{
    protected $title;

    protected $items = [];

    /**
     * Begin modeling a new process.
     * 
     * @param string $title The title of the new process.
     */
    public function __construct($title = '')
    {
        $this->title = trim($title);
    }

    public function count()
    {
        return count($this->items);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Add a new node to the process.
     * 
     * @param string $id Unique ID of the node.
     * @return Node Created node instance.
     */
    public function node($id)
    {
        if (isset($this->items[$id])) {
            throw new \RuntimeException(sprintf('Duplicate item ID: "%s"', $id));
        }
        
        return $this->items[$id] = new Node($id);
    }

    /**
     * Add a new pass-through node to the process.
     * 
     * @param string $id nique ID of the node.
     * @return Node Created node instance.
     */
    public function passNode($id)
    {
        return $this->node($id)->behavior(new PassBehavior());
    }

    /**
     * 
     * @param unknown $id
     */
    public function startNode($id)
    {
        return $this->passNode($id)->initial();
    }

    /**
     * Add a new transition between 2 nodes to the process.
     * 
     * @param string $id Unique ID of the transition.
     * @param string $from ID of the start node.
     * @param string $to ID of the end node.
     * @return Transition Created transition instance.
     */
    public function transition($id, $from, $to)
    {
        if (isset($this->items[$id])) {
            throw new \RuntimeException(sprintf('Duplicate item ID: "%s"', $id));
        }
        
        $transition = $this->items[$id] = new Transition($id, $from);
        $transition->to($to);
        
        return $transition;
    }

    public function validate()
    {
        $messages = [];
        
        return $messages;
    }

    /**
     * Build a process model from registered nodes and transitions.
     * 
     * @return ProcessModel
     */
    public function build()
    {
        return new ProcessModel($this->items, $this->title);
    }

    public function append(ProcessBuilder $builder)
    {
        foreach ($builder->items as $id => $item) {
            if (isset($this->items[$id])) {
                throw new \RuntimeException(sprintf('Duplicate item ID: "%s"', $id));
            }
            
            $this->items[$id] = $item;
        }
        
        return $this;
    }
}
