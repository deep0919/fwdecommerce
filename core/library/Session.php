<?php
/**
 * Core session.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Session extends ArrayInterface implements REST
{
	// Internal properties.
	protected $__session_maxlifetime;
	protected $__session_cache;
	protected $__session_data;
	
	// Singleton instance.
	static $instance;

	/**
	 * Set session variables via object syntax.
	 */
	function __set ($name, $value)
	{
		// Ignore internal properties.
		if (!isset($this->{$name}))
		{
			$_SESSION[$name] = $value;
			$this[$name] = $value;
		}
		else
		{
			$this->{$name} = $value;
		}
	}

	/**
	 * Get session keys with object/array syntax.
	 */
	function & __get ($name)
	{
		// Ignore internal properties.
		if (!isset($this->{$name}))
		{
			$result =& $_SESSION[$name];
		}
		else
		{
			$result =& $this->{$name};
		}

		return $result;
	}
	function offsetGet ($name)
	{
		$result =& $_SESSION[$name];
		
		return $result;
	}

	/**
	 * Setup session.
	 */
	function __construct ($params = null)
	{
		// Enable sessions by HTTP request only.
		if ($_SERVER['HTTP_HOST'] && !self::$instance)
		{
			register_shutdown_function('session_write_close');
			$this->__session_maxlifetime = intval(ini_get('session.gc_maxlifetime'));
			$this->__session_data = null;

			// Session params by config.
			if ($params)
			{
				// Session domain sharing.
				if ($params['share_domain'])
				{
					ini_set('session.cookie_domain', '.'.preg_replace('/(.*?)([^\.]+\.[^\.]+)$/', '\2', $_SERVER['HTTP_HOST']));
					unset($params['share_domain']);
				}

				// Directly set all ini.
				foreach ((array)$params as $key => $value)
				{
					ini_set('session.'.$key, $value);
				}
			}

			// Initiate cache sessions by config param session->use_cache.
			if ($params['use_cache'])
			{
				$this->__session_cache = true;

				// Internal save handlers?
				session_set_save_handler(
					array(&$this, 'open'),
					array(&$this, 'close'),
					array(&$this, 'read'),
					array(&$this, 'write'),
					array(&$this, 'destroy'),
					array(&$this, 'gc')
				);
			}

			// Start.
			session_start();
			
			// Cronstruct arrayinterface.
			parent::__construct($_SESSION);
			
			// Save session id.
			$this->id = session_id();
			
			// Singleton.
			self::$instance =& $this;
		}
		
		return self::$instance;
	}
	
	/**
	 * Get a resource from session.
	 */
	function get ($id = null, $data = null, $stack = null, $query = null)
	{
		$target =& $this;
		
		if ($id)
		{
			$target =& $target[$id];
		
			if (is_array($stack))
			{
				foreach ($stack as $index)
				{
					// Walk through the session/stack;
					if (is_array($target[$index]))
					{
						($index ? $target =& $target[$index] : false);
					}
				}
			}
		
			if (!$target)
			{
				if ($data[':put'])
				{
					return $this->put($id, $data[':put'], $stack, $query);
				}
			}
		}
			
		return $target;
	}

	/**
	 * Delete a resource from session.
	 */
	function post ($id = null, $data = null, $stack = null, $query = null)
	{
		$target =& $this;
		
		if ($id)
		{
			$target[$id] = $data;
		}
		if (is_array($stack))
		{
			for ($i = 0; $i < count($stack); $i++)
			{
				// Last?
				if ($i == count($stack)-1)
				{
					$target[$stack[$i]] = $data;
					
					if (is_array($target[$stack[$i]]))
					{
						$target[$stack[$i]][] = $data;
					}
					else
					{
						$target[$stack[$i]] = array($target, $data);
					}
				}
				else
				{
					// Walk through session/stack.
					if (is_array($target[$stack[$i]]))
					{
						($stack[$i] ? $target =& $target[$stack[$i]] : false);
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Put a resource into session.
	 */
	function put ($id = null, $data = null, $stack = null, $query = null)
	{
		$target =& $this;
		
		if ($id)
		{
			$target[$id] = $data;
		}
		else
		{
			foreach ((array)$data as $key => $value)
			{
				$target[$key] = $value;
			}
		}
		if (is_array($stack))
		{
			for ($i = 0; $i < count($stack); $i++)
			{
				// Last?
				if ($i == count($stack)-1)
				{
					$target[$stack[$i]] = $data;
				}
				else
				{
					// Walk through session/stack.
					if (is_array($target[$stack[$i]]))
					{
						($stack[$i] ? $target =& $target[$stack[$i]] : false);
					}
				}
			}
		}
		
		return $data;
	}
	
	/**
	 * Delete a resource from session.
	 */
	function delete ($id = null)
	{
		if ($id)
		{
			unset($this->$id);
		}
			
		return null;
	}

	/**
	 * Open session.
	 */
	function open ($save_path, $session_name)
	{
		$session_id = session_id();

		if ($session_id !== '')
		{
			$this->__session_data = $this->read($session_id);
		}

		return true;
	}

	/**
	 * Close session.
	 */
	function close ()
	{
		return true;
	}

	/**
	 * Read session.
	 */
	function read ($session_id)
	{
		if ($this->__session_cache)
		{
			$data = Cache::get('session_'.$session_id);//get("/cache/sessions/{$session_id}");
		}

		return $data ?: false;
	}

	/**
	 * Write session data.
	 */
	function write ($session_id, $data)
	{
		if ($this->__session_cache)
		{
			$cache_result = Cache::put('session_'.$session_id, $data);//put("/cache/sessions/{$session_id}?expire={$this->__session_maxlifetime}", $data);
		}

		return $cache_result;
	}

	/**
	 * Destroy session.
	 */
	function destroy ($session_id)
	{
		if ($this->__session_cache)
		{
			Cache::delete('session_'.$session_id);//delete("/cache/sessions/{$session_id}");
		}

		return true;
	}

	/**
	 * Garbage collection.
	 */
	function gc ($maxlifetime)
	{
		// Cache will clean itself up.
		return true;
	}
}
