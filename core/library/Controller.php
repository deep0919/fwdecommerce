<?php
/**
 * Core controller.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Controller {

	public $request;
	public $params;
	public $session;
	public $cache;
	public $view;
	
	// Default controller options.
	public $default_layout;
	public $default_view;

	/**
	 * Constructor.
	 */
	function __construct ($route = null)
	{
		// Reference app config.
		$this->config = $this->config ?: Request::$config;

		// Access request wrapper.
		$this->request = $this->request ?: new Request(array(
			'domain' => $route['domain'],
			'path' => $route['path'],
			'controller' => $route['controller'],
			'args' => $route['args'],
			'action' => $route['action'] ?: $this->config->app['default_action'],
			'output' => $route['output'] ?: $this->config->app['default_output'],
			'layout' => $route['domain'] ?: $this->default_layout ?: $this->config->app['default_layout'],
			'view' => $route['view'] ?: $route['action'] ?: $this->default_view ?: $this->config->app['default_view']
		));
		
		// Routed view (default controller)?
		if ($route['view'])
		{
			$this->route_view($route['view']);
		}
		
		// Ajax layout?
		if ($this->request->ajax)
		{
			$this->request->layout = $this->ajax_layout ?: $this->config->app['ajax_layout'];
		}

		// Wrap get and post into params.
		$this->params = $this->params ?: new ArrayInterface(array_merge((array)$_POST, (array)$_GET));
		
		// Attach files to params.
		$this->params['_files'] = $_FILES;

		// Access cache wrapper.
		$this->cache = $this->Cache = ($this->cache ?: new Cache);

		// Access session wrapper.
		$this->session = $this->Session = ($this->session ?: new Session($this->config->session));

		// Create view with path data.
		$this->view = $this->view ?: new View(array(
			'path' => $route['path'],
			'name' => $route['controller']
		));
		
		// Restore messages.
		$this->restore_messages();
	}
	
	/**
	 * Auto load models as they are requested by the controller.
	 */
	function __get ($name)
	{
		if (empty($name))
		{
			return null;
		}
		
		// Auto-load class instances.
		if (in_array($name, (array)Request::$available_models) || get_parent_class($name) == 'Model')
		{
			if (!$this->{$name})
			{
				$this->{$name} = new $name;
				
				if (!in_array($name, (array)$this->request->models))
				{
					$this->request->models[] = $name;
				}
			}
		}
		
		return $this->{$name};
	}
	
	/**
	 * Native REST call.
	 */
	function __rest ($method, $resource, $data = null)
	{
		// Mirror HTTP methods.
		$method = strtoupper($method);
		if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE')))
		{
			throw new Exception("Unsupported REST method '{$method}'");
		}

		// Parse params and create resource stack.
		$url = parse_url((string)$resource);
		$stack = explode('/', ltrim($url['path'], '/'));
		
		// Is stack empty?
		if (empty($stack[0]))
		{
			return null;
		}

		// Validate resource exists. Camelcase by convention.
		$target = camelize($stack[0]);
		if (!class_exists($target) && !class_exists(($target = pluralize($target))))
		{
			throw new Exception("Unknown resource '{$stack[0]}' in '{$method} {$resource}'");
		}
		
		// Shift first resource elements off the stack.
		array_shift($stack);
		$id = array_shift($stack);
		
		// Make sure target class exists.
		if (!Request::$controller->{$target})
		{
			throw new Exception("Resource '{$target}' is not known by controller");
		}
		elseif (!is_object(Request::$controller->{$target}))
		{
			throw new Exception("Resource '{$target}' is not a valid object");
		}
		elseif (!(Request::$controller->{$target} instanceof REST))
		{
			throw new Exception("Resource '{$target}' does not implement REST");
		}
		
		// Parse query?
		if (!empty($url['query']))
		{
			parse_str($url['query'], $query);
			
			// Extract expire from query before merge.
			if (array_key_exists('expire', $query))
			{
				$expire = $query['expire'];
				unset($query['expire']);
			}
			
			// Merge data over query params?
			if (is_array($data))
			{
				$data = array_merge($query, (array)$data);
			}
			if ($expire)
			{
				$query['expire'] = $expire;
			}
		}
		
		// Get from cache?
		if ($query['cache'] > 0 && $method == 'GET')
		{
			// Cache time in seconds.
			$cache_time = $query['cache'];
			$cache_path = "/cache/{$method}{$url['path']}";
			$cache_stack = explode('/', ltrim($url['path'], '/'));
			$cache_id = $method;
			
			if ($result = Request::$controller->cache->get($cache_id, $data, $cache_stack, $query))
			{
				return $result;
			}
		}
		
		// Clear errors?
		if (method_exists(Request::$controller->{$target}, 'clear_errors'))
		{
			Request::$controller->{$target}->clear_errors();
		}
		
		// Invoke the method.
		$method = strtolower($method);
		$result = Request::$controller->{$target}->{$method}($id, $data, $stack, $query);
	
		// Check for errors?
		if (method_exists(Request::$controller->{$target}, 'get_errors'))
		{
			if ($errors = Request::$controller->{$target}->get_errors())
			{
				$result = array();
				$result['errors'] = $errors;
			}
		}
		
		// Save uri to result.
		if ($result instanceof ModelResource && $result->uri() == "")
		{
			$result->uri($url['path']);
		}
		
		// Save result to cache?
		if ($cache_time)
		{
			put("{$cache_path}?expire={$cache_time}", $result);
		}

		return $result;
	}
	
	/**
	 * Route a view path to view file.
	 */
	function route_view ($view)
	{
		// Hidden views.
		$view = str_replace('/_', '/', $view);
		
		// Attempt to route the URI to a specific view when controller is default.
		$view_parts = explode('/', trim($view, '/'));
		
		// Default?
		if ($view_parts[0] == null)
		{
			$view_parts[0] = $this->config->app['default_action'];
		}
		
		$output = $this->request->output;
		$args = array();
		$path = $this->request->path;

		// Test URI parts until we find an existing view.
		foreach ((array)$view_parts as $part)
		{
			$test_path = '/'.implode('/', $view_parts);
			
			$part = array_pop($view_parts);
			
			// Try different view paths.
			$views = array(
				$this->config->app['view_path']."{$path}{$test_path}/index.{$output}",
				$this->config->app['view_path']."{$path}/index{$test_path}.{$output}",
				$this->config->app['view_path']."{$path}{$test_path}.{$output}"
			);
			foreach ($views as $view)
			{
				if (is_file($view))
				{
					// View found!
					$view_found = $view;
					break(2);
				}
			}

			// Put test part in args.
			array_unshift($args, $part);
		}

		// Replace default controller view/args/action.
		$this->request->view = $view_found ?: $view;
		$this->request->args = $args;
		$this->request->action = $part;
	}

	/**
	 * Set the view.
	 */
	function set_view ($view)
	{
		if (is_array($view))
		{
			$view['absolute'] = true;
		}

		return $this->request->view = url($view);
	}

	/**
	 * Get the view.
	 */
	function get_view ()
	{
		$view = $this->request->view;
		
		// Append output?
		if ($view && strpos($view, '.') === false)
		{
			$view .= '.'.$this->request->output;
		}
		
		return $view;
	}

	/**
	 * Set the view layout.
	 */
	function set_layout ($layout)
	{
		$this->request->layout = $layout;
	}

	/**
	 * Get the view layout.
	 */
	function get_layout ()
	{
		return $this->request->layout;
	}

	/**
	 * Redirect app url.
	 */
	function redirect ($location = '/')
	{
		if (is_array($location))
		{
			$location = url($location);
		}
		
		if (!$location = trigger('controller', 'redirect', $location))
		{
			return false;
		}

		Request::$controller->preserve_messages();

		if (Request::$controller->request->ajax)
		{
			$location .= (strpos($location, '?') === false ? '?__ajax' : '&__ajax');
		}

		header("Location: {$location}");
		exit;
	}

	/**
	 * Presere all messages in session.
	 */
	function preserve_messages ()
	{
		if ($this->messages_exist())
		{
			$this->session->_preserved_messages = array(
				'errors' => $this->request->errors,
				'warnings' => $this->request->warnings,
				'notices' => $this->request->notices,
			);
		}

		foreach ((array)$this->request->models as $model)
		{
			if (method_exists($model, 'get_errors'))
			{
				$this->session->_preserved_model_errors[$model] = $this->{$model}->get_errors();
			}
		}
	}
	
	/**
	 * Restore all messages from session.
	 */
	function restore_messages ()
	{
		if ($this->session->_preserved_messages)
		{
			foreach ($this->session->_preserved_messages as $type => $messages)
			{
				$this->set_message($messages, $type);
			}

			$this->session->_preserved_messages = null;
		}

		if ($this->session->_preserved_model_errors)
		{
			foreach ($this->session->_preserved_model_errors as $model => $errors)
			{
				$this->$model->errors = $errors;
			}

			$this->session->_preserved_model_errors = null;
		}
	}
	
	/**
	 * Render controller output with layout/view.
	 */
	function render ($params = null, $print = true)
	{
		$controller = Request::$controller;
		
		// Override request view?
		if (is_array($params))
		{
			if (isset($params['view']))
			{
				$controller->set_view($params['view']);
			}
			if (isset($params['layout']))
			{
				$controller->set_layout($params['layout']);
			}
		}
		else if (is_string($params))
		{
			$controller->set_view($params);
			$params = array('view' => $params);
		}
		
		// Assign public controller properties to view.
		foreach ($controller as $key => &$value)
		{
			$controller->view->assign($key, $value);
		}
		
		// Destroy last reference.
		unset($value);

		ob_start();

		// Display view.
		if ($params['view'] = $controller->get_view())
		{
			// Trigger render event.
			$params = trigger('controller', 'render', $params, $controller);
			
			if (is_file($params['view']))
			{
				// Get content result from view.
				$content = '';
				$result = $controller->view->render($params);
				
				// Boolean?
				if ($result == "false" || $result == "true")
				{
					$content = ob_get_clean();
				}
				// String?
				elseif ($result && is_string($result))
				{
					$content = $result;
					ob_get_clean();
				}
				// Returned status code?
				elseif (is_int($result) && $result >= 200)
				{
					$content = ob_get_clean();
					header('HTTP/1.1 '.($result));
				}
				else
				{
					$content = ob_get_clean();
				}
			}
			else
			{
				$view = str_replace('//', '/', $params['view']);
				throw new Exception("Page not found ({$view})", 404);
			}
		}

		// Render layout?
		if ($layout = $controller->get_layout())
		{
			$path = $params['path'] ?: $controller->request->path;
			$output = $params['output'] ?: $controller->request->output;
			$domain = $params['domain'] ?: $controller->request->domain;
			
			$layout_files = array(
				$controller->config->app['view_path']."/layouts/{$layout}.{$output}",
				$controller->config->app['view_path']."{$path}/layouts/{$layout}.{$output}"
			);
			
			// Domain route matches layout name?
			if ($domain == $layout)
			{
				$layout_is_default = true;
				$layout_files[] = $controller->config->app['view_path']."{$path}/layouts/default.{$output}";
			}
			
			foreach ($layout_files as $layout_file)
			{
				if (is_file($layout_file))
				{
					// Layout found!
					$layout_found = $layout_file;
					break;
				}
			}
			if ($layout_found)
			{
				// Assign content for layout.
				$controller->view->assign('content_for_layout', $content);
				
				// Render layout as view.
				$controller->view->render($layout_found);
			}
			elseif ($layout_is_default)
			{
				// View without default layout.
				print $content;
			}
			else
			{
				// Layout not found, error.
				$layout = str_replace('//', '/', $layout);
				throw new Exception("Layout not found ({$layout}.{$output})");
			}
		}
		else 
		{
			// View without layout.
			print $content;
		}

		// Print final output.
		$output = ob_get_contents();
		ob_end_clean();

		if ($print)
		{
			print $output;
		}
		
		return $output;
	}

	/**
	 * Set page notice message(s).
	 */
	function notice ($notice = 'Unknown notice', $redirect = null)
	{
		Request::$controller->set_message($notice, 'notices', $redirect);

		return;
	}

	/**
	 * Set page warning message(s).
	 */
	function warn ($warn = 'Unknown warning', $redirect = null)
	{
		Request::$controller->set_message($warn, 'warnings', $redirect);

		return;
	}

	/**
	 * Set page error message(s).
	 */
	function error ($error = 'Unknown error', $redirect = null)
	{
		Request::$controller->set_message($error, 'errors', $redirect);

		return;
	}

	/**
	 * Set page message(s) of any severity.
	 */
	function set_message ($message, $type, $redirect = null)
	{
		// Make sure type is valid.
		if (!in_array($type, array('notices', 'warnings', 'errors')))
		{
			return false;
		}

		if (is_string($message))
		{
			$this->request[$type] = array_merge((array)$this->request[$type], array($message));
			$this->request->messages[] = $message;
		}

		if (is_array($message))
		{
			$this->request[$type] = array_merge((array)$this->request[$type], $message);
			$this->request->messages = array_merge((array)$this->request->messages, $message);
		}

		if ($redirect)
		{
			$this->redirect($redirect);
		}

		return;
	}

	/**
	 * Boolean check for existing page messages.
	 */
	function messages_exist ()
	{
		return (count($this->request->messages)
				|| count($this->request->notices)
				|| count($this->request->warnings)
				|| count($this->request->errors)) ? true : false;
	}

	/**
	 * Check for existing errors, and optionally related model errors.
	 */
	function errors_exist ($related = true)
	{
		if ($related)
		{
			foreach ((array)$this->request->models as $model)
			{
				if (method_exists($this->{$model}, 'errors_exist') && $this->{$model}->errors_exist($related))
				{
					return true;
				}
			}
		}

		return count($this->request->errors) ? true : false;
	}

	/**
	 * Clear all existing messages, and optionally all related model errors.
	 */
	public function clear_messages ($related = false)
	{
		$this->request->messages = array();
		$this->request->notices = array();
		$this->request->warnings = array();
		$this->request->errors = array();

		if ($related)
		{
			$this->clear_errors($related);
		}

		return;
	}

	/**
	 * Clear all existing errors, and optionally all related model errors.
	 */
	public function clear_errors ($related = false)
	{
		$this->request->errors = array();

		if ($related)
		{
			foreach ((array)$this->request->models as $model)
			{
				if (method_exists($this->{$model}, 'clear_errors'))
				{
					$this->{$model}->clear_errors();
				}
			}
		}

		return;
	}
	
	/**
	 * Default error handler.
	 */
	function handle_error ($code, $message, $file, $line, $globals, $is_exception = false)
	{
		// Indicate whether this method has started.
		static $error_handled = false;
		
		$default_output = $this->config->app['default_output'];
		$default_view = $this->config->app['default_action'];

		// Show error cover page?
		if (!$error_handled)
		{
			$error_handled = true;
			
			// App error in debug mode?
			if ($this->config->app['debug'] && $code == 500)
			{
				return true;
			}
			else
			{
				$path = $this->request->path;
			
				// Does a view exist for this error code?
				$views = array(
					$this->config->app['view_path']."{$path}/{$code}.{$default_output}",
					$this->config->app['view_path']."{$path}/_{$code}.{$default_output}",
					$this->config->app['view_path']."{$path}/{$default_view}/{$code}.{$default_output}",
					$this->config->app['view_path']."{$path}/{$default_view}/_{$code}.{$default_output}"
				);
				foreach ($views as $view)
				{
					if (is_file($view))
					{
						// View found!
						$error_view = $view;
						break;
					}
				}
				if ($error_view)
				{
					// Render code specific view.
					$this->render($error_view);
					
					// Error code changed in view?
					$code = $this->request['status'] ?: $code;
					
					// Prevent default if code is not 500?
					if ($code != 500)
					{
						//header('HTTP/1.1 '.($code));
						return false;
					}
				}
			}
		}
		
		// Log?
		if ($this->config->app['log'])
		{
			$log_code = ($code == 404) ? 404 : 500;
			$this->log("{$log_code} {$message} at {$_SERVER['REQUEST_URI']} in {$file} on line {$line}");
		}
		
		// Continue to default error handler.
		return true;
	}

	/**
	 * Log something.
	 */
	static function log ($string, $context = 'app', $level = 'notice', $increment = true)
	{
		static $log_counter = array();
		
		if ($increment)
		{
			$log_counter[$context]++;
		}

		$file = APP_ROOT."logs/{$context}.".date('Y-m-d').".log";

		$exists = is_file($file);

		if (!$exists || is_writeable($file))
		{
			$result = file_put_contents(
				$file,
				"{$log_counter[$context]} [{$level}]: ".date('H:i:s').": ({$_SERVER['REMOTE_ADDR']}) {$string}\n",
				$exists ? FILE_APPEND : null
			);

			if ($result && !$exists)
			{
				chmod($file, 0755);
			}
		}

		return true;
	}

	/**
	 * Parse a controller string into parts (name, class, path).
	 */
	static function get_parts ($controller, $path)
	{
		$result = array();
		
		$parts = explode('/', $controller);
		if (!$result['name'] = array_pop($parts))
		{
			$result['name'] = Request::$config->app['default_controller'];
		}
		
		$result['class'] = camelize($result['name']).'Controller';
		
		$result['path'] = (!$parts || $parts[0]) ? $path.array_shift($parts) : '/';
		foreach ($parts as $part)
		{
			if ($part)
			{
				$result['path'] .= "{$part}/";
			}
		}
	
		return $result;
	}
}
