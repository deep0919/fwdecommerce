<?php
/**
 * Trigger callback for an event.
 * Returns the first argument by default.
 *
 *		Usage example:
 *			$value1 = trigger('target', 'eventname', $value1, $value2 [, $value3 ...]);
 */
function trigger ($target, $event = null)
{
	global $__event_binds;
	
	// Get args.
	$args = array_slice(func_get_args(), 2);
	
	$events = bind_parse_events($target, $event);
	foreach ($events as $event)
	{
		$key = $event['key'];
		$pre = $event['pre'];
		$name = $event['name'];
		
		// Prep args.
		$result = count($args) ? $args[0] : 0;
		
		// If pre is 'on', trigger 'before' binds first.
		if ($pre == 'on')
		{
			$pre_set = array('before', 'on');
		}
		else
		{
			$pre_set = array($pre);
		}
		
		// Reset cancel trigger.
		$GLOBALS['__bind_stop'] = false;
		
		// Trigger callback[s].
		foreach ($pre_set as $pre)
		{
			foreach ((array)$__event_binds[$key][$pre][$name] as $event_level)
			{
				foreach ((array)$event_level as $callback)
				{
					$return = call_user_func_array($callback, $args);
					
					// Stop propagation?
					if ($return === false)
					{
						return false;
					}
					
					// Chain result.
					if (count($args) > 1)
					{
						$result = isset($return) ? ($args[0] = $return) : $args[0];
					}
					else
					{
						$result++;
					}
					
					// Stop chain?
					if ($GLOBALS['__bind_stop'])
					{
						$GLOBALS['__bind_stop'] = false;
						return $return;
					}
				}
			}
		}
	}
	if (empty($events))
	{
		$result = count($args) ? $args[0] : 0;
	}
	
	return $result;
}
