<?php
/**
 * Core request.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Request extends ArrayInterface
{
	// App environment/config.
	static $env;
	static $config;
	static $env_config;
	
	// Controller stack.
	static $stack;
	
	// Currently active controller.
	static $controller;
	
	// Models available to the app.
	static $available_models;

	// Models available to the app.
	static $available_helpers;
	
	// Plugins available to the app.
	static $available_plugins;
	
	/**
	 * Constructor.
	 */
	function __construct ($params = null)
	{
		$config = self::$config;

		// Request param defaults.
		$default_params = array(
			'env' => self::$env,
			'uri' => $_SERVER['REQUEST_URI'],
			'layout' => $config->app['default_layout'],
			'view' => $config->app['default_action'],
			'output' => $config->app['default_output'],
			'models' => array(),
			'messages' => array(),
			'notices' => array(),
			'warnings' => array(),
			'errors' => array(),
			'method' => strtolower($_SERVER['REQUEST_METHOD']),
			'post' => ($_SERVER['REQUEST_METHOD'] == 'POST') ? true : false,
			'get' => ($_SERVER['REQUEST_METHOD'] == 'GET') ? true : false,
			'ajax' => ($_SERVER["HTTP_X_REQUESTED_WITH"] == 'XMLHttpRequest' || isset($_REQUEST['__ajax'])) ? true : false,
			'host' => $_SERVER['HTTP_HOST']
		);
		
		// Merge defaults with $_SERVER.
		foreach (array_merge($_SERVER, $default_params) as $key => $val)
		{
			$key = strtolower($key);
			
			if ($params[$key] === null)
			{
				$params[$key] = $val;	
			}
		}
		
		// Apply params to request.
		parent::__construct($params);
	}
	
	/**
	 * Start the application.
	 */
	static function start ($dispatch = true)
	{
		ob_start();
		
		// Set error reporting level.
		error_reporting(E_ALL ^ E_NOTICE);
		
		// Setup default error handelers.
		set_error_handler(array('Request', 'default_error_handler'), E_ALL);
		set_exception_handler(array('Request', 'default_exception_handler'));
	
		// Setup autoloader.
		spl_autoload_register(array('Request', 'autoload'));
		
		// Load app config.
		self::$config = new Config('app');
		$config =& self::$config;
		
		// Get current environment (default local).
		self::$env = is_file(APP_ROOT.'config/.env')
			? trim(file_get_contents(APP_ROOT.'config/.env'))
			: 'local';
		
		// Load environment config.
		if (is_file(APP_ROOT.'config/'.self::$env.'.yml'))
		{
			self::$env_config = new Config(self::$env);
			$env_config =& self::$env_config;
		
			// Merge environment config into app config.
			$config = Config::merge($config, $env_config);
		}
		
		// Setup app defaults.
		$default_app_config = array(
			'view_path' => APP_ROOT.'app/templates/',
			'public_path' => APP_ROOT.'public/',
			'default_controller' => 'index',
			'default_action' => 'index',
			'default_output' => 'html',
			'default_layout' => 'default',
			'default_locale' => 'en_US.UTF-8',
			'ajax_layout' => null
		);
		$config->app = Config::merge($default_app_config, $config->app);
		
		// Setup default locale.
		setlocale(LC_ALL, $config->app['default_locale']);
		
		// Setup default route.
		if (!is_array($config->routes))
		{
			$config->routes = array();
		}
		array_push($config->routes, array(
			'/'
		));
		array_push($config->routes, array(
			'/:controller/:action/*'
		));
	
		// Include core and app helpers.
		self::include_helpers(APP_ROOT.'app/helpers/');
		self::include_helpers(APP_ROOT.'core/helpers/');

		// Include core and app models.
		self::include_models(APP_ROOT.'app/models/', false);
		self::include_models(APP_ROOT.'core/models/', false);
		
		// Include core and app plugins.
		self::include_plugins(APP_ROOT.'app/plugins/');
		self::include_plugins(APP_ROOT.'core/plugins/');
		
		// Dispatch request?
		if ($dispatch)
		{
			return self::dispatch($_SERVER['REQUEST_URI'] ?: $_SERVER['argv'][1]);
		}
	}


	/**
	 * Dispatch a controller/request.
	 */
	static function dispatch ($params, $render = true)
	{
		$config = self::$config;
		
		$uri = url($params);
		
		// Trigger dispatch event.
		$uri = trigger('request', 'dispatch', $uri, $config);
		
		// Match domain route?
		if ($config->domain_routes)
		{
			if ($_SERVER['HTTP_HOST'])
			{
				foreach ((array)$config->domain_routes as $name => $domain_match)
				{
					if (preg_match('/'.$domain_match.'$/', $_SERVER['HTTP_HOST']))
					{
						$domain = $name;
						break;
					}
				}
			}
			else if (preg_match('/^\/?([^\:\/]+)[\:\/]+/', $uri, $match))
			{
				$name = $match[1];
				if ($config->domain_routes[$name])
				{
					$uri = preg_replace('/\/?'.$name.'[\:\/]+/', '/', $uri);
					$domain = $name;
				}
			}
		}

		// Match URI to a route.
		foreach ($config->routes as $route)
		{
			if (!is_array($route))
			{
				throw new Exception('App route is busted');
			}

			$route_uri = $route_exp = array_shift($route);

			// Limit route by domain?
			if ($route['domain'] && $domain != $route['domain'])
			{
				continue;
			}

			// Named binds?
			if (strpos($route_uri, ':') !== false)
			{
				// Create a regular expression to check for valid route, start with controller (special).
				$route_exp = str_replace(":controller", '?(.*)', $route_uri);

				// Bind requirements.
				foreach ((array)$route['requirements'] as $bind_name => $exp)
				{
					$route_exp = preg_replace("/:{$bind_name}/", "({$exp})", $route_exp);
				}

				// All other binds.
				$route_exp = preg_replace("/:([^\/]+)/", '([^/]+)', $route_exp);
			}

			// Separator.
			$route_exp = str_replace('/', '\/', $route_exp);

			// Wildcards.
			$route_exp = str_replace('/*', "/?(.*)", $route_exp);

			// Check if URI matches route.
			if (preg_match("/^{$route_exp}$/", $uri))
			{
				// Find controller in URI?
				if (strpos($route_uri, ":controller") !== false)
				{
					$controller_exp = str_replace(":controller", '?(.*)', $route_uri);
					$controller_exp = str_replace('/', '\/', preg_replace("/:([^\/]+)/", '?[^/]*', $controller_exp));

					if (preg_match_all("/^{$controller_exp}/", $uri, $controller_matches))
					{
						$controller_test_path = $controller_matches[1][0];
					}
				}
				
				// Make sure we have a place to start with the controller path.
				if (!$controller_test_path)
				{
					$controller_test_path = $route['controller'];
				}

				$uri_parts = explode('/', trim($controller_test_path, '/'));

				// Test URI parts until we find an existing controller (start with domain route name).
				$test_path = $domain ? "/{$domain}" : '';
				for ($i = 0; $i < count($uri_parts); $i++)
				{
					$test_path .= '/'.$uri_parts[$i];
					
					// Try to match dir default first.
					$parts = Controller::get_parts("{$test_path}", $controller_path);
					if (is_dir(APP_ROOT."app/controllers{$test_path}"))
					{
						$parts_dir = Controller::get_parts("{$test_path}/", $controller_path);
						if (is_file(APP_ROOT."app/controllers{$parts_dir['path']}{$parts_dir['class']}.php"))
						{
							$parts = $parts_dir;
							$found = true;
						}
					}
					if ($found || is_file(APP_ROOT."app/controllers{$parts['path']}{$parts['class']}.php"))
					{
						// Controller found.
						$controller = $parts;
						$controller_path = $controller['path'];
						$controller_name = $controller['name'];
						$controller_class = $controller['class'];
						
						// Remove domain route name from route uri?
						if ($domain)
						{
							$controller_is_default = $domain == $controller_name;
							$replace = preg_replace('/^\/'.$domain.'/', '', $test_path);
						}
						else
						{
							$replace = $test_path;
						}

						// Rewrite route uri for the rest of the binds.
						$route_uri = str_replace('/:controller', $replace, $route_uri);
						
						break;
					}

					// If nothing was found, try the default controller.
					if ($domain)
					{
						if (($i + 1) == count($uri_parts) && $uri_parts[$i] !== $domain)
						{
							$test_path = '';
							$uri_parts[] = $domain;
						}
					}
					else
					{
						if (($i + 1) == count($uri_parts) && $uri_parts[$i] !== $config->app['default_controller'])
						{
							$uri_parts[] = $config->app['default_controller'];
						}
					}
				}
				
				// Output?
				if (preg_match('/\/[^\/]+\.([^\/]+)$/', $uri, $output_matches))
				{
					$output = $output_matches[1];
					$uri = substr($uri, 0, strrpos($uri, '.'));
				}

				// Find values for all other URI binds.
				$binds = $route;
				if (preg_match_all("/:([^\/]+)/", $route_uri, $bind_matches) || strpos($route_uri, '*') !== false)
				{
					$route_exp = $route_uri;

					// Bind requirements.
					foreach ((array)$route['requirements'] as $bind_name => $exp)
					{
						$route_exp = preg_replace("/:{$bind_name}/", "?({$exp})", $route_exp);
					}
				
					// All other binds.
					$route_exp = str_replace('/', '\/', preg_replace("/:([^\/]+)/", '?([^/]*)', $route_exp));

					// Wildcards.
					$route_exp = str_replace('/*', "/?(.*)", $route_exp);

					// Pull bind values out of URI.
					if (preg_match_all("/^{$route_exp}/", $uri, $value_matches))
					{
						foreach((array)$bind_matches[1] as $key => $bind_name)
						{
							$binds[$bind_name] = $value_matches[$key+1][0];
							unset($value_matches[$key+1]);
						}
						
						// Remaining URI.
						if ($end_value = array_pop($value_matches))
						{
							$uri_remaining = "/{$end_value[0]}";
						}
					}	
				}

				// Action bind?
				$action = $binds['action'];
				
				// View bind?
				$view = $binds['view'];

				// Unset special parameters.
				unset($binds['controller']);
				unset($binds['action']);
				unset($binds['requirements']);
				unset($binds['view']);

				// Check if we have binds left to match to action arguments.
				if (count($binds) && $controller && $action && @include_once(APP_ROOT."app/controllers{$controller_path}{$controller_class}.php"))
				{
					list($method_name) = explode('.', underscore($action));
					if (method_exists($controller_class, $method_name) && $method = new ReflectionMethod($controller_class, $method_name))
					{
						$args = $method->getParameters();

						$arg_string = '';
						$skipped = 1;
						foreach ($args as $num => $arg)
						{
							if (isset($binds[$arg->name]))
							{
								$arg_string .= str_repeat('/', $skipped);
								$arg_string .= $binds[$arg->name];
								$skipped = 1;
							}
							else
							{
								$skipped++;
							}
						}
					}
				}
				// No binds left, just append the rest of URI as arguments.
				else
				{
					$arg_string = preg_replace("/^{$route_exp}/", '', $uri);
				}

				// Create action arguments array for action call.
				if ($arg_string.$uri_remaining != '/')
				{
					$args = explode('/', substr($arg_string.$uri_remaining, 1));
				}
				break;
			}
		}
		
		// Controller route defined.
		$route = array(
			'domain' => $domain,
			'path' => $controller_path ?: "/{$domain}/",
			'controller' => $controller_name,
			'action' => $action,
			'output' => $output,
			'args' => $args
		);
		
		// Controller found?
		if ($controller)
		{
			// Require and create controller.
			require_once APP_ROOT."app/controllers{$controller_path}{$controller_class}.php";
		}
		else
		{
			// Determine view by bind or uri.
			$route['view'] = $view ?: $uri;
			
			// AppController or Controller.
			$controller_class = class_exists('AppController') ? 'AppController' : 'Controller';
		}
		
		// Create controller instance.
		$controller = new $controller_class($route);
		
		// Push controller onto stack.
		self::$controller = &$controller;
		self::$stack[] = self::$controller;
		
		// Trigger new controller event.
		$controller = trigger('controller', 'new', $controller);
		
		// Invoke controller action (method).
		if (method_exists($controller, $controller->request->action))
		{
			$result = call_user_func_array(array(&$controller, $controller->request->action), (array)$controller->request->args);
		}
		else if (method_exists($controller, '__default'))
		{
			$result = call_user_func_array(array(&$controller, '__default'), (array)$controller->request->args);
		}

		// Returned an HTTP status code?
		if (is_int($result))
		{
			// Redirection?
			if ($result >= 300 && $result < 400)
			{
				exit;
			}
			// Client or server error?
			else if ($result >= 400 && $result < 600)
			{
				throw new Exception("{$controller_class}::{$action}() returned code {$result}", $result);
			}
		}
		elseif ($result)
		{
			print $result;
			exit;
		}
		
		// Render output?
		if ($render)
		{
			$controller->render();
		}
		
		// Pop controller, return it.
		return array_pop(self::$stack);
	}
	
	/**
	 * Autoload classes.
	 */
	static function autoload ($class_name)
	{
		// Start with model.
		require_once APP_ROOT."core/library/Model.php";
		
		if (!class_exists($class_name))
		{
			$class_files = array(
				APP_ROOT."app/models/{$class_name}.php",
				APP_ROOT."core/models/{$class_name}.php",
				APP_ROOT."app/library/{$class_name}.php",
				APP_ROOT."core/library/{$class_name}.php",
				APP_ROOT."app/controllers/{$class_name}.php",
				APP_ROOT."core/controllers/{$class_name}.php",
			);
			foreach ($class_files as $class_file)
			{
				if (is_file($class_file))
				{
					require_once $class_file;
				}
			}
		}
	}
	
	/**
	 * Include available helpers by file path.
	 */
	static function include_helpers ($path, $load = true)
	{
		$handle = opendir($path);
		while (($entry = readdir($handle)) !== false)
		{
			$file_name = explode('.', $entry);
			
			if ($file_name[1] != 'php')
			{
				continue;
			}
			if (in_array($file_name[0], (array)self::$available_helpers))
			{
				continue;
			}
			
			if ($load)
			{
				include_once "{$path}{$entry}";
			}
			
			self::$available_helpers[] = $file_name[0];
		}
	}

	/**
	 * Include available models by file path.
	 */
	static function include_models ($path, $load = true)
	{
		$handle = opendir($path);
		while (($entry = readdir($handle)) !== false)
		{
			$file_name = explode('.', $entry);
			
			if ($file_name[1] != 'php')
			{
				continue;
			}
			if (in_array($file_name[0], (array)self::$available_models))
			{
				continue;
			}
			
			if ($load)
			{
				include_once "{$path}{$entry}";
			}
			
			self::$available_models[] = $file_name[0];
		}
	}
	
	/**
	 * Include available plugins by file path.
	 */
	static function include_plugins ($path, $load = true)
	{
		$handle = opendir($path);
		while (($entry = readdir($handle)) !== false)
		{
			if (is_dir($path.$entry))
			{
				$plugin_path = "{$path}{$entry}/";
				$entry = "{$entry}.php";
				
				if (!is_file($plugin_path.$entry))
				{
					continue;
				}
			}
			else
			{
				$plugin_path = $path;
			}
			
			$file_name = explode('.', $entry);
			
			if ($file_name[1] != 'php')
			{
				continue;
			}
			if (in_array($file_name[0], (array)self::$available_plugins))
			{
				continue;
			}
			
			if ($load)
			{
				include_once "{$plugin_path}{$entry}";
			}
			
			self::$available_plugins[] = $file_name[0];
		}
	}
	
	/**
	 * Transforms an array to index by a specific key.
	 *
	 * @param {array} $array Array to index
	 * @param {string} $key Index to key the array by
	 * @return {array} Array keyed by the new index
	 */
	static function key_array_by ($array, $key)
	{
		$array_by = array();
		foreach ((array)$array as $item)
		{
			if (!isset($item[$key]))
			{
				return $array;
			}
			$array_by[$item[$key]] = $item;
		}
	
		return $array_by;
	}
	
	/**
	 * Catch default exceptions missed by Controller::handle_error(), pass to default_error_handler().
	 */
	static function default_exception_handler ($e)
	{
		try
		{
			self::default_error_handler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), $GLOBALS, true);
		}
		catch (Exception $e)
		{
			print "Exception thrown by exception handler: '".$e->getMessage()."' on line ".$e->getLine();
		}
	}
	
	/**
	 * Catch default errors missed by Controller::handle_error().
	 */
	static function default_error_handler ($code, $message, $file, $line, $globals, $is_exception = false)
	{
		// Ignore errors if PHP is not set to report them.
		if (!$is_exception)
		{
			$code = ($code & error_reporting());
			if (!$code)
			{
				return;
			}
		}
		// Check if we have a controller to handle the error.
		if (is_object(self::$controller) && method_exists(self::$controller, 'handle_error'))
		{
			// Exit if catch returns false.
			if (self::$controller->handle_error($code, $message, $file, $line, $globals, $is_exception) === false)
			{
				exit;
			}
		}
		
		// Send error to server logs.
		error_log("App ".($is_exception ? 'Exception' : 'Error').": {$message} in {$file} on line {$line} (code: {$code})");
		
		// Generic internal server error.
		header('HTTP/1.1 500 Internal Server Error');
		
		// Check if App is set to display errors or not.
		if (self::$config && !self::$config->app['debug'])
		{
			exit;
		}
		
		// Check if PHP is set to display errors or not.
		if (!ini_get('display_errors'))
		{
			exit;
		}
	
		// Otherwise, continue to standard error handling...
		$type = $is_exception ? 'Exception' : 'Error';
		$type_code = $is_exception && $code ? ": {$code}" : '';
		switch ($code)
		{
			case E_ERROR:   		$type_name = 'Error'; break;
			case E_WARNING: 		$type_name = 'Warning'; break;
			case E_PARSE:   		$type_name = 'Parse Error'; break;
			case E_NOTICE:  		$type_name = 'Notice'; break;
			case E_CORE_ERROR:  	$type_name = 'Core Error'; break;
			case E_CORE_WARNING:	$type_name = 'Core Warning'; break;
			case E_COMPILE_ERROR:   $type_name = 'Compile Error'; break;
			case E_COMPILE_WARNING: $type_name = 'Compile Warning'; break;
			case E_USER_ERROR:  	$type_name = 'Error'; break;
			case E_USER_WARNING:	$type_name = 'Warning'; break;
			case E_USER_NOTICE: 	$type_name = 'Notice'; break;
			case E_STRICT:  		$type_name = 'Strict'; break;
			default:				$type_name = $is_exception ? 'Exception' : 'Unknown';
		}
	
		ob_end_clean();
	
		$backtrace = debug_backtrace();
		array_shift($backtrace);
	
		?>
		<html>
			<head>
				<title>Application <?php echo $type; ?></title>
				<style>
					body {
						font: 16px Arial;
	
					}
					div.callStack {
						background-color: #eee;
						padding: 10px;
						margin-top: 10px;
					}
					i.message {
						color: #f00;
						white-space: normal;
						line-height: 22px;
					}
				</style>
			</head>
			<h1>Application <?php echo $type; ?></h1>
			<ul>
				<li><b>Message:</b> (<?php echo $type_name; ?><?php echo $type_code; ?>) <pre><i class="message"><?php echo $message; ?></i></pre></li>
				<li><b>File:</b> <?php echo $file; ?> on line <i><b><?php echo $line; ?></b></i></li>
				<?php if (!$is_exception): ?>
					<li><b>Call Stack:</b>
						<div class="callStack">
							<ol>
								
							<?php for ($i = (count($backtrace) - 1); $i >= 0; $i--): if ($backtrace[$i]['function'] == 'trigger_error') continue; ?>
								<li>
									<i><?php echo $backtrace[$i]['function']; ?>()</i> in
									<?php echo $backtrace[$i]['file']; ?> on line
									<i><b><?php echo $backtrace[$i]['line']; ?></b></i>
								</li>
							<?php endfor; ?>
							</ol>
						</div>
					</li>
				<?php endif; ?>
			</ul>
		</html>
		<?php
	
		die();
	}
}

/**
 * ArrayInterface enhances ArrayIterator access methods.
 */
class ArrayInterface extends ArrayIterator
{
	function & __get ($key)
	{
		$result =& $this[$key];
		return $result;
	}
	
	function __set ($key, $val)
	{
		return parent::offsetSet($key, $val);
	}
	
	function offsetSet ($key, $val)
	{
		parent::offsetSet($key, $val);
		$this->$key = $val;
	}
	
	function dump ($return = false)
	{
		return print_r($this->getArrayCopy(), $return);
	}
}

/**
 * Interface for RESTful resources.
 */
interface REST
{
	public function get ($id = null, $data = null, $stack = null);
	
	public function post ($id = null, $data = null, $stack = null);
	
	public function put ($id = null, $data = null, $stack = null);
	
	public function delete ($id = null);
}
