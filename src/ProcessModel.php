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

use KoolKode\Util\UUID;

/**
 * Process models are directed graphs consisting of nodes and transitions.
 * 
 * @author Martin Schröder
 */
class ProcessModel
{
	protected $id;
	protected $title;
	protected $items;
	
	public function __construct(array $items, $title = '', UUID $id = NULL)
	{
		$this->id = ($id === NULL) ? UUID::createRandom() : $id;
		$this->title = trim($title);
		
		foreach($items as $item)
		{
			if($item instanceof Node)
			{
				if(NULL === $item->getBehavior())
				{
					throw new \RuntimeException(sprintf('Node "%s" does not declare a behavior', $item->getId()));
				}
			}
		}
		
		$this->items = $items;
	}
	
	public function __clone()
	{
		foreach($this->items as & $item)
		{
			$item = clone $item;
		} 
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function getTitle()
	{
		return $this->title;
	}
	
	public function getItems()
	{
		return $this->items;
	}
	
	public function findItem($id)
	{
		if($id instanceof Item)
		{
			return $id;
		}
		
		return $this->items[$id];
	}
	
	public function findNodes()
	{
		$nodes = [];
		
		foreach($this->items as $item)
		{
			if($item instanceof Node)
			{
				$nodes[] = $item;
			}
		}
		
		return $nodes;
	}
	
	public function findNode($id)
	{
		if($id instanceof Node)
		{
			$node = $id;
		}
		else
		{
			if(empty($this->items[$id]))
			{
				throw new \OutOfBoundsException(sprintf('No such node found: "%s"', $id));
			}
			
			$node = $this->items[$id];
		}
		
		if(!$node instanceof Node)
		{
			throw new \OutOfBoundsException(sprintf('No such node found: "%s"', $id));
		}
		
		return $node;
	}
	
	public function findTransition($id)
	{
		if($id instanceof Transition)
		{
			return $id;
		}
		
		if(empty($this->items[$id]))
		{
			throw new \OutOfBoundsException(sprintf('No such transition found: "%s"', $id));
		}
			
		$trans = $this->items[$id];
	
		if(!$trans instanceof Transition)
		{
			throw new \OutOfBoundsException(sprintf('No such transition found: "%s"', $id));
		}
	
		return $trans;
	}
	
	public function findIncomingTransitions($id)
	{
		if($id instanceof Node)
		{
			$id = $id->getId();
		}
		
		$transitions = [];
	
		foreach($this->items as $item)
		{
			if($item instanceof Transition && $item->getTo() == $id)
			{
				$transitions[] = $item;
			}
		}
	
		return $transitions;
	}
	
	public function findOutgoingTransitions($id)
	{
		if($id instanceof Node)
		{
			$id = $id->getId();
		}
		
		$transitions = [];
		
		foreach($this->items as $item)
		{
			if($item instanceof Transition && $item->getFrom() == $id)
			{
				$transitions[] = $item;
			}
		}
		
		return $transitions;
	}
	
	public function findInitialNodes()
	{
		$items = [];
		
		foreach($this->items as $item)
		{
			if($item instanceof Node && $item->isInitial())
			{
				$items[] = $item;
			}
		}
		
		return $items;
	}
	
	public function findStartNodes()
	{
		$nodes = [];
		
		foreach($this->items as $item)
		{
			if($item instanceof Node)
			{
				$in = $this->findIncomingTransitions($item->getId());
				
				if(empty($in))
				{
					$nodes[] = $item;
				}
			}
		}
		
		return $nodes;
	}
}
