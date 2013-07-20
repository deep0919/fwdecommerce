<?php
/**
 * Core view.
 * Initializes and wraps template framework.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class View
{
	// Path relative to views directory.
	public $path;
	
	// Namespace of views (i.e. controller name).
	public $name;

	// Smarty instance.
	static $smarty;

	/**
	 * Constructor.
	 */
	function __construct ($params = null)
	{
		$this->path = $params['path'];
		$this->name = $params['name'];

		// Init smarty.
		if (!self::$smarty)
		{
			// Extend smarty package.
			if (!defined('SMARTY_DIR'))
			{
				define('SMARTY_DIR', APP_ROOT.'core/library/smarty/libs/');
				require(APP_ROOT.'core/library/smarty/libs/Smarty.class.php');
			}
			
			self::$smarty = new Smarty;
			self::$smarty->setCacheDir(APP_ROOT.'core/cache/');
			self::$smarty->setCompileDir(APP_ROOT.'core/cache/');
			self::$smarty->setConfigDir(APP_ROOT.'core/library/smarty/config/');
			self::$smarty->force_compile = Request::$config->smarty['force_compile'] ? true : false;
			self::$smarty->caching = Request::$config->smarty['caching'] ? true : false;
		}

		// Init helpers.
		foreach ((array)Request::$available_helpers as $helper)
		{
			if (class_exists($helper))
			{
				$class = new $helper;
				self::$smarty->registerObject($helper, $class);
			}
			
			// If we loaded a function helper...
			if (function_exists($helper))
			{
				self::$smarty->registerPlugin('function', $helper, $helper);
				self::$smarty->registerPlugin('modifier', $helper, $helper);
			}
			
			// Register compiler functions.
			self::register_compilers();
		}
	}

	/**
	 * Assign a variable to the view.
	 */
	function assign ($key, $value)
	{
		return self::$smarty->assign($key, $value);
	}

	/**
	 * Retrieve one or all variables from the view.
	 */
	function retrieve ($key = null)
	{
		return self::$smarty->getTemplateVars($key);
	}

	/**
	 * Render a view template.
	 */
	function render ($params, $assign = true)
	{
		static $__depth;
		
		$config = Request::$config;
		
		// Trigger render event.
		$params = trigger('view', 'render', $params, $this);

		// Params as array or string.
		if (is_array($params))
		{
			$view_path = $params['view'];
		}
		else
		{
			$view_path = url($params);
			$params = array($params);
		}

		// Append output?
		if (strpos($view_path, '.') === false)
		{
			$view_path .= '.'.($params['output'] ?: $config->app['default_output']);
		}

		// Determine absolute view pathing.
		$pos = strpos($view_path, '/');

		// Slash, i.e. 'controller/view'
		if ($pos > 0)
		{
			$view_path = "{$this->path}{$view_path}";
		}
		// No slash, i.e. 'view'
		else if ($pos === false)
		{
			$view_path = "{$this->path}{$this->name}/{$view_path}";
		}

		// Assign extra parameters to template?
		if ($assign && is_array($params))
		{
			$__saved = array();
			foreach ($params as $key => $value)
			{
				$__saved[$key] = $this->retrieve($key);
				$this->assign($key, $value);
			}
		}

		// Save original view path.
		$orig_view_path = $view_path;
		
		// Relative path?
		if (!is_file($view_path))
		{
			$sep = ($view_path[0] != '/') ? '/' : '';
			$orig_view_path = $view_path = $config->app['view_path']."{$sep}{$view_path}";
		
			// Hidden view? starts with "_"
			// @TODO: Make render routing more consistent with controller view routing.
			if (!is_file($view_path) && $__depth)
			{
				$view_path = preg_replace('/\/([^\/]+)$/', '/_$1', $view_path);
			
				// Index hidden view?
				if (!is_file($view_path))
				{
					$view_path = preg_replace('/\/([^\/]+)$/', '/index/$1', $view_path);
				}
			}
			
			// Index public view?
			if (!is_file($view_path))
			{
				$view_path = preg_replace('/\/[\_]?([^\/]+)$/', '/$1', $view_path);
			}
		}

		// View exists?
		if (is_file($view_path))
		{
			$__depth++;
			
			// Include plain PHP file?
			if (substr($view_path, -4) == '.php')
			{
				extract($this->retrieve());
				$result = require($view_path);
			}
			// Display template.
			else
			{
				self::$smarty->display($view_path);
			}
			
			$__depth--;
		}
		// Required to exist? (default = true)
		elseif ($params['required'] !== false)
		{
			$view_path = str_replace('//', '/', $orig_view_path);
			throw new Exception("View not found ({$view_path})");
		}

		// Restore saved variables.
		foreach ((array)$__saved as $key => $value)
		{
			$this->assign($key, $value);
		}
		
		// Determine result from view.
		$result = $result ?: $GLOBALS['__view_result'];
		unset($GLOBALS['__view_result']);

		// Trigger after:render event, return result.
		return trigger('view', 'after:render', $result, $this);
	}
	
	/**
	 * Register special compile functions.
	 */
	static function register_compilers ()
	{
		static $registered = false;
		
		if ($registered)
		{
			return true;
		}
			
		// Render helper.
		self::$smarty->registerPlugin('compiler', 'render', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('view')
			));
			
			return '<?php render('.View::serialize_php($params).') ?>'
				.'<?php if (isset($GLOBALS[\'__view_result\'])) { return; } ?>';
		});
		
		// Extend helper. It's like render with no output.
		self::$smarty->registerPlugin('compiler', 'extend', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('view', 'export_var')
			));
			
			return '<?php ob_start(); render('.View::serialize_php($params).'); ob_end_clean(); ?>'
				.'<?php if (isset($GLOBALS[\'__view_result\'])) { return; } ?>';
		});
				
		// Args helper.
		self::$smarty->registerPlugin('compiler', 'args', function ($args, $smarty)
		{
			$params = View::parse_args($args);
			
			foreach ((array)$params as $key => $val)
			{
				if (is_numeric($key))
				{
					$params[$key] = str_replace('$_smarty_tpl->tpl_vars[', '', $val);
					$params[$key] = str_replace(']->value', '', $params[$key]);
				}
			}
			
			return '<?php args('.View::serialize_php($params).', $_smarty_tpl) ?>';
		});
				
		// Redirect helper.
		self::$smarty->registerPlugin('compiler', 'redirect', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('url')
			));
			
			return '<?php redirect('.View::serialize_php($params).') ?>';
		});
		
		// GET helper.
		self::$smarty->registerPlugin('compiler', 'get', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('result', 'from', 'resource', 'data')
			));
			
			// Params
			$resource = $params['resource'];
			$result = $params['result'];
			$data = $params['data'];
			
			// Attributes as data?
			if ($data == null)
			{
				unset(
					$params['result'],
					$params['from'],
					$params['resource'],
					$params['data']
				);
				$data = View::serialize_php($params);
			}
			
			return "<?php {$result} = get({$resource}, {$data}); ?>";
		});
		
		// PUT helper.
		self::$smarty->registerPlugin('compiler', 'put', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('data', 'in', 'resource', 'result')
			));
			
			// Params
			$resource = $params['resource'];
			$result = $params['result'];
			$data = $params['data'];
			
			// Attributes as data?
			if ($data == null)
			{
				unset(
					$params['result'],
					$params['in'],
					$params['resource'],
					$params['data']
				);
				$data = View::serialize_php($params);
			}
			
			return "<?php ".($result ? "{$result} =" : '')." put({$resource}, {$data}); ?>";
		});
		
		// POST helper.
		self::$smarty->registerPlugin('compiler', 'post', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('data', 'in', 'resource', 'result')
			));
			
			// Params
			$resource = $params['resource'];
			$result = $params['result'];
			$data = $params['data'];
			
			// Attributes as data?
			if ($data == null)
			{
				unset(
					$params['result'],
					$params['in'],
					$params['resource'],
					$params['data']
				);
				$data = View::serialize_php($params);
			}
			
			return "<?php ".($result ? "{$result} =" : '')." post({$resource}, {$data}); ?>";
		});
		
		// DELETE helper.
		self::$smarty->registerPlugin('compiler', 'delete', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('resource', 'result')
			));
			
			// Params
			$resource = $params['resource'];
			$result = $params['result'];
			
			return "<?php ".($result ? "{$result} =" : '')." delete({$resource}); ?>";
		});
		
		// Pluralize helper.
		self::$smarty->registerPlugin('compiler', 'pluralize', function ($args, $smarty)
		{
			$params = View::parse_args($args, array(
				'tags' => array('word', 'if_many')
			));
			
			return '<?php echo pluralize('.View::serialize_php($params).'); ?>';
		});
		
		// Return helper.
		self::$smarty->registerPlugin('compiler', 'return', function ($args, $smarty)
		{
			if ($args[0] !== null)
			{
				// Save result to global context for render() to extract.
				return '<?php $GLOBALS[\'__view_result\'] = '.$args[0].'; return; ?>';
			}
			else
			{
				return '<?php return; ?>';
			}
		});
		
		return $registered = true;
	}
	
	/**
	 * Get attributes from smarty compiler arguments.
	 */
	function parse_args ($args, $options = null)
	{
		$params = array();
		$tagged = 0;
		$count = 0;
		foreach ((array)$args as $key => $val)
		{
			
			if (is_numeric($key))
			{
				
				// Flag?
				if (($flag = preg_replace('/[^a-z0-9\_\-\.]/i', '', $val)) && in_array($flag, (array)$options['flags']))
				{
					$key = $flag;
					$val = $val[0] == "!" ? false : true;
				}
				// Short tag.
				else if ($options['tags'][$tagged])
				{
					$key = $options['tags'][$tagged++];
				}
				else
				{
					$key = $count++;
				}
			}
			
			$params[$key] = $val;
		}
		
		return $params;
	}

	/**
	 * Serialize variable as PHP.
	 */
	static function serialize_php ($var)
	{
		if (is_array($var))
		{
			$count = 0;
			foreach ($var as $key => $val)
			{
				$key = $key ?: $count++;
				$key = is_numeric($key) ? $key : '"'.$key.'"';
				$output .= ($output ? ', ' : '')."$key => ".View::serialize_php($val);
			}
			return "array($output)";
		}
		else if (is_bool($var))
		{
			return $var ? "true" : "false";
		}
		else
		{
			return $var;
		}
	}
}
