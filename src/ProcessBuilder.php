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

class ProcessBuilder
{
	protected $title;
	protected $items = [];
	
	public function __construct($title = '')
	{
		$this->title = trim($title);
	}
	
	public function node($id)
	{
		return $this->items[$id] = new Node($id);
	}
	
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
	
	public function build()
	{
		return new ProcessDefinition($this->items, $this->title);
	}
}
