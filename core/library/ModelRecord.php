<?php
/**
 * ModelRecord.
 * Provides an array-like interface to a single record.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class ModelRecord extends ModelResource
{
	private $called;
	
	/**
	 * Constructor.
	 */
	function __construct (&$values, $model_name)
	{
		if (is_array($values))
		{
			parent::__construct($values, $model_name);
		}
	}
	
	/**
	 * Get field value.
	 */
	function offsetGet ($field)
	{
		$values = $this->values();
	
		// Callback?
		if (!isset($this->called[$field]) && is_callable($this->model()->fields[$field]))
		{
			$this->called[$field] = $values[$field] ?: false;
			$this->called[$field] = call_user_func($this->model()->fields[$field], $this, $this->model());
		}
		
		// Callback result?
		if (isset($this->called[$field]))
		{
			return $this->called[$field];
		}
		// Internal result?
		if (isset($values[$field]))
		{
			return $values[$field];
		}
		// Static default result?
		else if ($this->model()->fields[$field] && !is_callable($this->model()->fields[$field]))
		{
			return $this->model()->fields[$field];
		}

		return null;
	}
	
	/**
	 * Get raw record values.
	 */
	function values ()
	{
		return $this->getArrayCopy();
	}
	
	/**
	 * Dump raw record values.
	 */
	function dump ($return = false)
	{
		$dump = $this->values();
		
		foreach ((array)$this->model()->fields as $key => $val)
		{
			// Is it a callback?
			if (!$dump[$key] && is_callable($val) && is_object($val))
			{
				$related = call_user_func($val, $dump, $this->model());
				if ($related instanceof ModelResource)
				{
					$dump[$key] = $related->uri();
				}
				else
				{
					$dump[$key] = $related;
				}
			}
		}
		return print_r($dump, $return);
	}
}
