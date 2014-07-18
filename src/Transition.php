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

class Transition extends Item
{
	protected $from;
	protected $to;
	protected $triggers = [];
	
	public function __construct($id, $from)
	{
		parent::__construct($id);
		
		$this->from = (string)$from;
	}
	
	public function __toString()
	{
		return sprintf('transition(%s)', $this->id);
	}
	
	public function getFrom()
	{
		return $this->from;
	}
	
	public function getTo()
	{
		return $this->to;
	}
	
	public function to($to)
	{
		$this->to = $to;
	}
	
	public function trigger(TriggerInterface $trigger)
	{
		if($this->triggers === NULL)
		{
			$this->triggers = [$trigger];
		}
		else
		{
			$this->triggers[] = $trigger;
		}
	}
	
	public function isEnabled(Execution $execution)
	{
		if(!empty($this->triggers))
		{
			foreach($this->triggers as $trigger)
			{
				if(!$trigger->isEnabled($execution))
				{
					return false;
				}
			}
		}
		
		return true;
	}
}
