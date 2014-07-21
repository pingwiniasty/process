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
 * The process builder is a convenient ultility for programmatic construction of
 * process definitions.
 * 
 * @author Martin Schröder
 */
class ProcessBuilder
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
	
	/**
	 * Add a new node to the process.
	 * 
	 * @param string $id Unique ID of the node.
	 * @return Node Created node instance.
	 */
	public function node($id)
	{
		return $this->items[$id] = new Node($id);
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
	 * Build a process definition from registered nodes and transitions.
	 * 
	 * @return ProcessDefinition
	 */
	public function build()
	{
		return new ProcessDefinition($this->items, $this->title);
	}
}
