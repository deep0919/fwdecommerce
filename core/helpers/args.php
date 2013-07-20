<?php
/**
 * Extract arguments from the current Request URI.
 *
 * 		Usage example:
 *			(Request URI: /blog/2012/11/15/example-blog)
 *			(View: /blog.html)
 *
 *			{args $year $month $day $slug} # 2012 11 15 example-blog
 *			{get $blog from "/channels/blog/entries/$slug"}
 *			...
 */
function args ($pattern = null, $view_tpl = null)
{
	if (empty($pattern))
	{
		return;
	}

	// Array of patterns?
	if (is_array($pattern))
	{
		$parts = array();
		$defaults = array();
		$key = 0;
		foreach ($pattern as $id => $name)
		{
			if (!is_numeric($id))
			{
				$defaults[$key] = $name;
				$name = $id;
			}
			
			$parts[$key] = preg_replace('/[^a-z0-9\_*\/]/i', '', $name);
			$key++;
		}
	}
	// String pattern.
	else
	{
		$pattern = preg_replace('/[^a-z0-9\_*\/]/i', '', $pattern);
		
		// Parse params and create resource stack.
		$parts = explode('/', trim($pattern, '/'));
	}
	
	// Apply pattern to current request context args.
	$args = (array)Request::$controller->request->args;
	$new_args = array();
	foreach ($parts as $key => $name)
	{
		// Greedy?
		if (strpos($name, '*') !== false)
		{
			$greedy = array_slice($args, $key);
			$name = str_replace('*', '', $name);
			$new_args[$name] = str_replace('*', '', implode('/', $greedy));
		}
		else
		{
			$new_args[$name] = $args[$key];
		}
		
		// Default value?
		if (!$new_args[$name] && $defaults[$key])
		{
			$new_args[$name] = $defaults[$key];
		}
		
		// Assign to view?
		if (isset($new_args[$name]))
		{
			if (is_object($view_tpl) && method_exists($view_tpl, 'assign'))
			{
				$view_tpl->assign($name, $new_args[$name]);
			}
		}
		
		// Greedy is the last arg.
		if ($greedy)
		{
			break;
		}
	}
	
	// Return instead of assign?
	if (!is_object($view_tpl))
	{
		return $new_args;
	}
}