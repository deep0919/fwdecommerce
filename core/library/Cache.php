<?php
/**
 * Core cache.
 * Cache backends binds to core cache events.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Cache implements REST
{
	/**
	 * Get a resource from cache.
	 */
	function get ($id = null, $data = null, $stack = null, $query = null)
	{
		$value = trigger('cache', 'get', null, $id, $query);
		
		$target =& $value;
		foreach ((array)$stack as $index)
		{
			if (!is_array($target))
			{
				$target = array($target);
			}
			($index ? $target =& $target[$index] : false);
		}
		
		if (!$target && is_array($data) && isset($data[':put']))
		{
			$this->put($id, $data[':put'], $stack, $query);
		}
		
		return $target;
	}

	/**
	 * Post a resource to cache.
	 */
	function post ($id = null, $data = null, $stack = null, $query = null)
	{
		$value = $this->get($id, $data, $stack, $query);
	
		$target =& $value;
		foreach ((array)$stack as $index)
		{
			if (!is_array($target))
			{
				$target = array($target);
			}
			$target =& $target[$index];
		}
	
		if ($value)
		{
			if (is_array($target))
			{
				$target[] = $data;
			}
			else
			{
				$target = array($target, $data);
			}
		}
		else
		{
			$value[] = $data;
		}
		
		return trigger('cache', 'post', $value, $id, $query);
	}

	/**
	 * Put a resource in cache.
	 */
	function put ($id = null, $data = null, $stack = null, $query = null)
	{
		$value = $this->get($id, $data, $stack, $query);
		
		$target =& $value;
		foreach ((array)$stack as $index)
		{
			if ($target && !is_array($target))
			{
				$target = array($target);
			}
			$target =& $target[$index];
		}
		
		$target = $data;
		
		return trigger('cache', 'put', $target, $id, $query);
	}
	
	/**
	 * Delete a resource from cache.
	 */
	function delete ($id = null, $data = null, $stack = null, $query = null)
	{
		$value = $this->get($id, $data);
	
		if (is_array($stack))
		{
			$target =& $value;
			foreach ((array)$stack as $index)
			{
				if (!is_array($target))
				{
					$target = array($target);
				}
				if (!isset($target[$index]))
				{
					return null;
				}
				$target =& $target[$index];
			}
		
			$target = null;
			
			return $this->put($id, $value);
		}
		else
		{
			return trigger('cache', 'delete', null, $id, $query);
		}
	}
}