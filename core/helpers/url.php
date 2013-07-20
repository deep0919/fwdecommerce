<?php
/**
 * URL path relative to current request path.
 * Note: Designed for use with controllers.
 *
 *		Usage example:
 *			{url controller="blogs"} # /blogs
 *			{url controller="blogs" action="list"} # /blogs/list
 *			{url controller="blogs" action="edit" id=$blog_id} # /blogs/edit/123
 */
function url ($params)
{
	// Controller path and name.
	$controller_path = Request::$controller->request->path;
	$controller_name = Request::$controller->request->controller;

	if (is_string($params))
	{
		$path = preg_replace("/[\?\&].*/", '', $params);
	}
	else if (is_array($params))
	{
	
		$controller = ($params['controller']) ? $params['controller'] : $controller_path.$controller_name;
		if ($controller[0] !== '/')
		{
			$controller = ($controller_path) ? "{$controller_path}{$controller}" : "/{$controller}";
		}
		
		if ($params['action'] && $params['action'] !== Request::$config->app['default_action'])
		{
			$action = $params['action'];
		}
		
		// Absolute path (default controller).
		if ($params['absolute'])
		{
			$action = $action ?: Request::$config->app['default_action'];
		}
		
		// Unset all expected params, leaving action args.
		unset($params['controller']);
		unset($params['action']);
		unset($params['absolute']);
		
		// If other params have been passed, try to align them with action args.
		$parts = Controller::get_parts($controller, $controller_path);
		if (count($params) && @include_once(APP_ROOT."app/controllers{$parts['path']}{$parts['class']}.php"))
		{
			$method_name = strtolower(str_replace('-', '_', ($action) ? $action : Request::$config->app['default_action']));
			if (method_exists($parts['class'], $method_name) && $method = new ReflectionMethod($parts['class'], $method_name))
			{
				$args = $method->getParameters();

				$arg_string = '';
				$skipped = 1;
				foreach ($args as $num => $arg)
				{
					if (isset($params[$arg->name]))
					{
						$arg_string .= str_repeat('/', $skipped);
						$arg_string .= $params[$arg->name];
						$skipped = 1;
					}
					else
					{
						$skipped++;
					}
				}
				if ($arg_string)
				{
					$action .= ($action) ? $arg_string : Request::$config->app['default_action'].$arg_string;
				}
			}
		}
		
		if (!$arg_string && $controller == Request::$config->app['default_controller'] && $action == Request::$config->app['default_action'])
		{
			$path = '/';
		}
		else
		{
			$path = $action ? "{$controller}/{$action}" : $controller; 
		}
	}

	return $path;
}
