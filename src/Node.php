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

use KoolKode\Process\Behavior\BehaviorInterface;

/**
 * A node is process element that is comparable to a place in a Petri-Net.
 * 
 * @author Martin Schröder
 */
class Node extends Item
{
	const FLAG_NONE = 0;
	const FLAG_INITIAL = 1;
	const FLAG_END = 2;
	
	protected $flags = self::FLAG_NONE;
	protected $behavior;
	
	public function __toString()
	{
		return sprintf('node(%s)', $this->id);
	}
	
	public function isInitial()
	{
		return ($this->flags & self::FLAG_INITIAL) != 0;
	}
	
	public function initial()
	{
		$this->flags |= self::FLAG_INITIAL;
		
		return $this;
	}
	
	/**
	 * Get the node's behavior or NULL when no custom behavior is available.
	 * 
	 * @return BehaviorInterface
	 */
	public function getBehavior()
	{
		return $this->behavior;
	}
	
	public function behavior(BehaviorInterface $behavior = NULL)
	{
		$this->behavior = $behavior;
		
		return $this;
	}
}
