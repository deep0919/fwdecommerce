<?php
/**
 * Bind callback to an event.
 *
 *		Usage example:
 *			bind('products', 'get', function ($event)
 *			{
 *				if ($event['data']['search'])
 *				{
 *					return my_custom_search($event['data']['search']);
 *				}
 *			});
 */
function bind ($target, $event, $callback = null, $level = 1)
{
	global $__event_binds;
	
	if (is_null($callback))
	{
		$callback = $event;
		$event = $target;
		$target = 0;
	}
	
	if (!is_callable($callback))
	{
		return false;
	}
	
	$events = bind_parse_events($target, $event);
	foreach ($events as $event)
	{
		$key = $event['key'];
		$pre = $event['pre'];
		$name = $event['name'];
		
		// Make sure it's only bound once.
		if (!is_array($__event_binds[$key][$pre][$name][$level]))
		{
			$__event_binds[$key][$pre][$name][$level] = array();
		}
		foreach ($__event_binds[$key][$pre][$name] as $event_level)
		{
			foreach ($event_level as $ex_callback)
			{
				if ($ex_callback === $callback)
				{
					return false;
				}
			}
		}
	
		// "Bind" the callback.
		$__event_binds[$key][$pre][$name][$level][] = $callback;
		
		// Sort levels.
		ksort($__event_binds[$key][$pre][$name]);
	}
	
	return true;
}

/**
 * Bind event formatter.
 */
function bind_parse_events ($target, $event = null)
{
	// Event arg optionally combined with target.
	if (is_null($event))
	{
		$event = $target;
		$target = 0;
	}
	else
	{
		// Convert object to class string.
		if (is_object($target))
		{
			$target = get_class($target);
		}
		
		// Target is case insensitive.
		if (is_string($target))
		{
			$target = strtolower($target);
		}
	}
	
	// Event format = [target.][pre:]event[,[pre:]event]
	$event = strtolower($event);
	$event = str_replace(' ', '', $event);
	$event_parts = explode(',', $event);
	foreach ($event_parts as $event)
	{
		// Target as part of event string?
		if ($target === 0)
		{
			// Target specified before '.'
			$target_parts = explode('.', $event);

			// Combine remaining '.' into event string.
			if ($target_parts[1])
			{
				$key = array_shift($target_parts);
				$event = implode('.', $target_parts);
			}
			else
			{
				$key = $target;
				$event = $target_parts[0];
			}
		}
		else
		{
			// Target as event key.
			$key = $target;
		}
			
		// Determine pre value.
		$pre_parts = explode(':', $event);
		$name = $pre_parts[1] ?: $pre_parts[0];
		$pre = $pre_parts[1] ? $pre_parts[0] : 'on';
		
		// Save parsed event.
		$parsed_events[] = array(
			'key' => $key,
			'pre' => $pre,
			'name' => $name
		);
	}
	
	return $parsed_events;
}

/**
 * Return value from bind callback and cancel trigger chain.
 */
function bind_stop ($result = null)
{
	$GLOBALS['__bind_stop'] = true;
	return $result;
}