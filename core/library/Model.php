<?php
/**
 * Core model.
 * App models extend this class.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Model implements REST
{
	// Database properties.
	public $adapter;
	public $database;
	public $db;
	
	// Model state.
	public $errors;
	
	// Model structure.
	public $pk;
	public $name;
	public $fields;
	public $search_fields;
	public $indexes;
	public $validate;
	public $binds;
	public $slug_pk;
	public $auto_increment;
	public $auto_increment_start;
	
	// Query options.
	public $query = array(
		'where' => null,
		'order' => null,
		'limit' => null,
		'page' => null
	);
	
	/**
	 * Constructor.
	 */
	function __construct ($params = null)
	{
		// Apply model definition.
		$params = array_merge((array)$this->define(), (array)$params);
		foreach ($params as $key => $val)
		{
			if (is_array($this->$key))
			{
				if (is_array($val))
				{
					$this->$key = array_merge($this->$key, $val);
				}
				else
				{
					$this->{$key}[] = $val;
				}
			}
			else
			{
				$this->$key = $val;
			}
		}
		
		// Apply default definition.
		foreach ((array)$this->define_default() as $key => $val)
		{
			if (empty($this->$key))
			{
				$this->$key = $val;
			}
			else if (is_array($this->$key))
			{
				if (is_array($val))
				{
					$this->$key = array_merge($val, $this->$key);
				}
				else
				{
					$this->{$key}[] = $val;
				}
			}
		}
		
		// Apply event binds.
		if ($this->binds)
		{
			foreach ((array)$this->binds as $event => $callback)
			{
				$this->bind($event, $callback);
			}
		}
		
		// Init db adapter?
		if (!$this->db)
		{
			if ($this->adapter)
			{
				$this->db = Database::get_adapter($this->adapter, array(
					'database' => $this->database,
					'name' => $this->name,
					'fields' => $this->fields,
					'search_fields' => $this->search_fields,
					'indexes' => $this->indexes,
					'pk' => $this->pk,
					'auto_increment' => $this->auto_increment,
					'auto_increment_start' => $this->auto_increment_start
				));
			}
			else
			{
				throw new Exception('Model adapter not specified for '.$this->name);
			}
		}
	}

	/**
	 * Trigger event on this model.
	 */
	function trigger ($event)
	{
		// Args for base model.
		$args = array_merge(array('Model'), func_get_args(), array($this));
		
		// Trigger event on base model.
		$result = call_user_func_array('trigger', $args);
		
		// Args for this model.
		array_shift($args);
		$args[1] = $result;
		$args = array_merge(array(get_class($this)), $args);

		// Trigger event on this model.
		return call_user_func_array('trigger', $args);
	}
	
	/**
	 * Bind event to this model.
	 */
	function bind ($event, $callback, $level = 0)
	{
		return bind(get_class($this), $event, $callback, $level);
	}
	
	/**
	 * Overriden to define model structure.
	 */
	function define ()
	{
		return array();
	}
	
	/**
	 * Define default validators, model structure, etc.
	 */
	function define_default ()
	{
		$class = new ReflectionClass($this);
		
		// Default model structure.
		return array(
			'pk' => 'id',
			'name' => $class->getName(),
			'query' => array(
				'page' => 1,
				'limit' => 25,
				'window' => 10,
				'order' => ($this->pk) ? "{$this->pk} DESC" : null
			),
			'binds' => array(
			
				// Validate that a field is not empty.
				'validate:required' => function ($value, $field, $params, $model)
				{
					if (!$params['if_empty'] || empty($params['values'][$params['if_empty']]))
					{
						if (is_string($value))
						{
							if ($value === '' && (empty($params['values'][$params['if_not_exists']])) && $value !== null)
							{
								$model->error($params['message'] ?: 'required', $field, $params['value_key']);
							}
						}
						else if (is_array($value))
						{
							foreach ($value as $key => $val)
							{
								if ($val === '')
								{
									$model->error($params['message'] ?: 'required', $field, $key);
								}
							}
						}
					}
		
					return $value;
				},
				
				// Validate e-mail address format.
				'validate:email-address' => function ($value, $field, $params, $model)
				{
					if ($value && !preg_match('/^([_a-z0-9-\+]+)(\.[_a-z0-9-\+]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i', trim($value)))
					{
						$model->error($params['message'] ?: 'invalid e-mail address', $field, $params['value_key']);
					}
		
					return $value;
				},
				
				// Validate confirm value.
				'validate:confirm' => function ($value, $field, $params, $model)
				{
					$confirm_value = $params['value'] ?: $params["{$field}_confirm"];
		
					if ($confirm_value !== null && $value !== $confirm_value)
					{
						$model->error($params['message'] ?: "does not match", $field, $params['value_key']);
					}
		
					return $value;
				},
				
				// Validate field length.
				'validate:length' => function ($value, $field, $params, $model)
				{
					if (empty($value))
					{
						return $value;
					}
					if (isset($params['exactly']))
					{
						if (strlen($value) != $params['exactly'])
						{
							$error = "invalid length ({$params['exactly']})";
						}
					}
					if (isset($params['min']) && isset($params['max']))
					{
						if (strlen($value) < $params['min'] || strlen($value) > $params['max'])
						{
							$error = "invalid length ({$params['min']}-{$params['max']}";
						}
					}
					if (isset($params['min']))
					{
						if (strlen($value) < $params['min'])
						{
							$error = "invalid length (min {$params['min']})";
						}
					}
					if (isset($params['max']))
					{
						if (strlen($value) > $params['max'])
						{
							$error = "invalid length (max {$params['max']})";
						}
					}
					if ($error)
					{
						$model->error(($params['message'] ?: $error), $field, $params['value_key']);
					}
		
					return $value;
				},
				
				// Validate field uniqueness.
				'validate:unique' => function ($value, $field, $params, $model)
				{
					if (empty($value) || ($params['action'] && !in_array($params['action'], array('insert', 'update', 'post', 'put'))))
					{
						return $value;
					}
		
					$fields = preg_split('/[\s]*,[\s]*/', $field);
		
					$test_values = array();
					foreach ($fields as $field_name)
					{
						$test_values[$field_name] = $params['values'][$field_name];
					}
		
					$record = $model->find_one(null, array('where' => $test_values, 'fields' => $model->pk));
		
					if ($record && $record[$model->pk] != $params['values'][$model->pk])
					{
						$model->error($params['message'] ?: "already exists", $field, $params['value_key']);
					}
		
					return $value;
				}
			)
		);
	}
	
	/**
	 * Trigger RESTful event binds on this model.
	 */
	function trigger_rest_binds ($method, $args)
	{
		// Event as iterator to effectively pass values by reference.
		$event = new ArrayIterator(array(
			'id' => $args[0],
			'data' => $args[1],
			'stack' => $args[2],
			'query' => $args[3]
		));
		
		$data =& $event['data'];
		
		// Convert iterator to array?
		if ($data instanceof ArrayIterator)
		{
			$data = iterator_to_array($data);
		}
		
		// Set flags by id?
		if (strpos($event['id'], ':') === 0)
		{
			foreach (explode(':', $event['id']) as $flag)
			{
				if (!empty($flag))
				{
					$data[":{$flag}"] = true;
				}
			}
			
			$event['id'] = null;
		}
		
		if ($event['stack'][0])
		{
			// METHOD.*
			$result = $this->trigger("{$method}.*", $event);
		
			if ($event['stack'][0] && ($result === $event))
			{
				$fk = strtolower(singularize($event['stack'][0])).'_id';
				$event[$fk] = $event['stack'][1];
				
				// METHOD.related
				$result = $this->trigger("{$method}.{$event['stack'][0]}", $event);
			}
		}
		else
		{
			// METHOD
			$result = $this->trigger($method, $event);
		}
		
		// Continue?
		if ($result === true)
		{
			$result = $event;
		}
		
		return $result;
	}
	
	/**
	 * Trigger RESTful after:event binds on this model.
	 */
	function trigger_rest_after_binds ($method, $record, $args)
	{
		$event = new ArrayIterator(array(
			'id' => $args[0],
			'data' => $args[1],
			'stack' => $args[2],
			'query' => $args[3],
			'orig' => $args[4]
		));
		
		if ($event['stack'][0])
		{
			// METHOD.*
			$result = $this->trigger("after:$method}.*", $record, $event);
			
			if ($event['stack'][0] && ($result === $record))
			{
				$fk = strtolower(singularize($event['stack'][0])).'_id';
				$event[$fk] = $event['stack'][1];
				
				// METHOD.related
				$result = $this->trigger("after:{$method}.{$event['stack'][0]}", $record, $event);
			}
		}
		else
		{
			// METHOD
			$result = $this->trigger("after:{$method}", $record, $event);
		}
		
		return $result;
	}
	
	/**
	 * Get resource (find one or many).
	 */
	function get ($id = null, $data = null, $stack = null, $query = null)
	{
		// Trigger REST binds.
		$event = $this->trigger_rest_binds("GET", func_get_args());
		if ($event === false || ($event instanceof ModelResource))
		{
			return $event;
		}
		elseif (is_array($event) && $event['result'])
		{
			return $event['result'];
		}
		else
		{
			$id = $event['id'];
			$data = $event['data'];
			$stack = $event['stack'];
			$query = $event['query'];
		}
		
		$params =& $data;
		
		// Validate and return?
		if (is_array($data) && $data[':validate'])
		{
			$this->validate($data, 'insert', $data[':validate']);
			return $data;
		}

		// Get from collection.
		if ($id === null)
		{
			// Get result with?
			if (isset($data[':with']))
			{
				if (is_array($data[':with']) || $data[':with'] instanceof ArrayIterator)
				{
					foreach ($data[':with'] as $id => $values)
					{
						if ($with_id = $values[$this->pk] ?: $get_id = $values[$this->slug_pk])
						{
							if ($result = $this->get($with_id))
							{
								$with_values = $result->values();
								$results[$id] = merge($with_values, $values);
							}
						}
						
						if (!$results[$id])
						{
							$results[$id] = $values;
						}
					}
				
					$result = $this->get_model_collection($results);
				}
				else
				{
					$result = null;
				}
			}
			// Count result?
			else if (isset($data[':count']))
			{
				$where = is_array($data[':count']) ? $data[':count'] : null;
				$result = $this->count($where, $params);
			}
			// First, last, one or many?
			else if (isset($data[':first']))
			{
				$where = is_array($data[':first']) ? $data[':first'] : null;
				$params['order'] = $params['order'] ?: "{$this->pk} ASC";
				$result = $this->find_one($where, $params);
			}
			else if (isset($data[':last']))
			{
				$where = is_array($data[':last']) ? $data[':last'] : null;
				$params['order'] = $params['order'] ?: "{$this->pk} DESC";
				$result = $this->find_one($where, $params);
			}
			else if (isset($data[':one']))
			{
				$where = is_array($data[':one']) ? $data[':one'] : null;
				$result = $this->find_one($where, $params);
			}
			else
			{
				$result = $this->paginate(null, $params);
			}
			
			// Record? Chain through stack.
			if ($stack && ($result instanceof ModelRecord))
			{
				foreach ($stack as $element)
				{
					$result = $result[$element] ?: $result[singularize($element)];
				}
			}
			
			// Call after:get event on each record individually.
			if ($result instanceof ModelCollection)
			{
				foreach ($result as $key => $val)
				{
					$result[$key] = $this->trigger_rest_after_binds('GET', $result[$key], array($id, $data, $stack, $query));
				}
			}
			else
			{
				$result = $this->trigger_rest_after_binds('GET', $result, array($id, $data, $stack, $query));
			}
			
			return $result;
		}
		// Empty ID?
		else if (!$id)
		{
			return null;
		}
		
		// Get single resource.
		if (is_numeric($id) || !$this->has_field($this->slug_pk))
		{
			$result = $this->find_one(array("{$this->pk}" => $id), $params);
		}
		else if ($this->slug_pk && $this->has_field($this->slug_pk))
		{
			$result = $this->find_one(array("{$this->slug_pk}" => $id), $params);
		}
			
		// Get by related resource.
		if ($stack && $result)
		{
			foreach ($stack as $element)
			{
				$result = $result[$element] ?: $result[singularize($element)];
			}
		}
		else if (!$result)
		{
			// Resource not found, put instead?
			if (isset($data[':put']))
			{
				return $this->put($id, $data[':put'], $stack);
			}
		}
		
		// Merge result with?
		if (isset($data[':with']) && $result)
		{
			$result = merge($result, $data[':with']);
		}
		
		if (!$this->errors)
		{
			return $this->trigger_rest_after_binds('GET', $result, array($id, $data, $stack, $query));
		}
	}
	
	/**
	 * Post resource (insert).
	 */
	function post ($id = null, $data = null, $stack = null, $query = null)
	{
		// Trigger REST binds.
		$event = $this->trigger_rest_binds("POST", func_get_args());
		if ($event === false || ($event instanceof ModelResource))
		{
			return $event;
		}
		elseif (is_array($event) && $event['result'])
		{
			return $event['result'];
		}
		else
		{
			$id = $event['id'];
			$data = $event['data'];
			$stack = $event['stack'];
		}
		
		// Validate before post?
		if (is_array($data) && $data[':validate'])
		{
			if (!$this->validate($data, 'post', $data[':validate']))
			{
				return false;
			}
			unset($data[':validate']);
		}
		
		// Post to related resource?
		if ($stack)
		{
			$record = $this->get($id);
			
			// Internal collection?
			if (($field = $stack[0]) && ($this->has_field($field, true) || isset($query[':internal'])))
			{	
				if ($stack[1] == null)
				{
					// Get internal values?
					if ($record instanceof ModelRecord)
					{
						$record = $record->values();
					}
					
					$values = $record[$field];
					
					// Auto index.
					if (empty($values))
					{
						$values[1] = $data;
					}
					else
					{
						$values[] = $data;
					}
					
					// Update record.
					if ($this->update(array("{$field}" => $values), array("{$this->pk}" => $id)))
					{
						$result = $data;
					}
				}
			}
			else
			{
				// Relate to pk.
				if ($record)
				{
					$fk = strtolower(singularize($this->name)).'_'.$this->pk;
					$data[$fk] = $record[$this->pk];
				}
				
				// Chain through stack.
				$next_uri = implode('/', $stack);
				return post("/{$next_uri}", $data);
			}
		}
		// Post to this resource?
		else if ($id == null)
		{
			$id = $this->insert($data);
			$result = $this->get($id);
		}
		
		if ($result && !$this->errors)
		{
			return $this->trigger_rest_after_binds('POST', $result, array($id, $data, $stack));
		}
	}
	
	/**
	 * Put resource (update or insert).
	 */
	function put ($id = null, $data = null, $stack = null, $query = null)
	{
		// Trigger REST binds.
		$event = $this->trigger_rest_binds("PUT", func_get_args());
		if ($event === false || ($event instanceof ModelResource))
		{
			return $event;
		}
		elseif (is_array($event) && $event['result'])
		{
			return $event['result'];
		}
		else
		{
			$id = $event['id'];
			$data = $event['data'];
			$stack = $event['stack'];
			$result = $data;
		}
		
		if (is_array($data))
		{
			// Delete?
			if ($data[':delete'])
			{
				return $this->delete($id, $data, $stack);
			}
		
			// Validate before post?
			if ($data[':validate'])
			{
				if (!$this->validate($data, 'put', $data[':validate']))
				{
					return false;
				}
				unset($data[':validate']);
			}
		}
		
		// Put to related resource?
		if ($id && $stack)
		{	
			$record = $this->get($id);
			
			// Internal field?
			if (($field = $stack[0]) && ($this->has_field($field, true) || isset($query[':internal'])))
			{
				// Collection?
				if (is_array($data) || $stack[1])
				{
					// Get internal values?
					if ($record instanceof ModelRecord)
					{
						$record = $record->values();
					}
					$values = $record[$field];
					
					// Turn scalar into array?
					if (!is_array($values) && !empty($values))
					{
						$values = array($values);
					}
					
					// Indexed?
					// @TODO: Chain through stack.
					if ($key = $stack[1])
					{
						$values[$key] = !is_array($data) ? $data : array_merge((array)$values[$key], $data);
					}
					else
					{
						$values = array_merge((array)$values, $data);
					}
				}
				else 
				{
					$values = $data;
				}

				if ($this->update(array("{$field}" => $values), array("{$this->pk}" => $id)))
				{
					$result = $data;
				}
			}
			else
			{
				// Relate to pk.
				if ($record && is_array($data))
				{
					$fk = strtolower(singularize($this->name)).'_'.$this->pk;
					$data[$fk] = $record[$this->pk];
				}
				
				// Chain through stack.
				$next_uri = implode('/', $stack);
				return put("/{$next_uri}", $data);
			}
		}
		// Put to this resource?
		else if ($id && is_array($data))
		{	
			// ID by pk or slug.
			if (is_numeric($id) || !$this->has_field($this->slug_pk))
			{
				$data[$this->pk] = $id;
				$where = array("{$this->pk}" => $id);
			}
			else if ($this->slug_pk && $this->has_field($this->slug_pk))
			{
				$data[$this->slug_pk] = $id;
				$where = array("{$this->slug_pk}" => $id);
			}
			
			// Merge data with existing?
			if ($where && $this->count($where) > 0)
			{
				$ex = $this->get($id);
				$data[$this->pk] = $data[$this->pk] ?: $ex[$this->pk];
				$this->update($data, $where);
			}
			// Not found, insert.
			else
			{
				$id = $this->insert($data);
			}
			
			$result = $this->get($id);
		}
		
		if ($result && !$this->errors)
		{
			return $this->trigger_rest_after_binds('PUT', $result, array($id, $data, $stack, null, $ex));
		}
	}
	
	/**
	 * Delete a record.
	 */
	function delete ($id = null, $data = null, $stack = null, $query = null)
	{
		// Trigger REST binds.
		$event = $this->trigger_rest_binds("DELETE", func_get_args());
		if ($event === false)
		{
			return $event;
		}
		elseif (is_array($event) && $event['result'])
		{
			return $event['result'];
		}
		else
		{
			$id = $event['id'];
			$data = $event['data'];
			$stack = $event['stack'];
		}
		
		$record = $this->get($id);
		
		// Delete from related resource?
		if ($id && $stack)
		{
			// Delete from internal collection?
			if (($field = $stack[0]) && ($this->has_field($field, true) || isset($query[':internal'])))
			{
				// Get internal values?
				if ($record instanceof ModelRecord)
				{
					$record = $record->values();
				}	
				$values = $record[$field];
				
				// Keyed?
				if ($key = $stack[1])
				{
					if (is_array($values))
					{
						unset($values[$key]);
					}
				}
				else
				{
					$values = null;
				}
				
				if ($this->update(array("{$field}" => $values), array("{$this->pk}" => $id)))
				{
					$result = $key ? $record[$field][$key] : $record[$field];
				}
			}
			else
			{	
				// Chain through stack.
				$next_uri = implode('/', $stack);
				return delete("/{$next_uri}", $data);
			}
		}
		// Delete by ID only.
		elseif ($id)
		{
			if ($record)
			{
				$where = array("{$this->pk}" => $record[$this->pk]);
				$this->db->delete($where);
				$result = $record;
			}
		}
		
		if ($result && !$this->errors)
		{
			return $this->trigger_rest_after_binds('DELETE', $result, array($id, $data, $stack));
		}
	}
	
	/**
	 * Insert a record.
	 */
	function insert ($values)
	{
		$values = $this->trigger('insert', $values);
		
		if (!$this->validate($values, 'insert'))
		{
			return false;
		}
		
		$values = $this->prepare_values($values);
		
		$result = $this->db->insert($values);
		
		return $this->trigger('after:insert', $result);
	}
	
	/**
	 * Update a record.
	 */
	function update ($values, $where, $return = false)
	{
		$values = $this->trigger('update', $values);
		
		if (!$this->validate($values, 'update'))
		{
			return false;
		}
		
		$values = $this->prepare_values($values);
		
		$result = $this->db->update($values, $where, $return);
		
		return $this->trigger('after:update', $result);
	}
	
	/**
	 * Prepare values for insert or update.
	 */
	function prepare_values ($values)
	{
		if ($values instanceof Iterator)
		{
			$values = iterator_to_array($values);
		}
		foreach ((array)$values as $key => $val)
		{
			if ($key[0] == ':')
			{
				unset($values[$key]);
			}
		}
		
		return $values;
	}
	
	/**
	 * Prepare where array for db.
	 */
	function prepare_where ($where, $params)
	{
		$where = array_merge((array)$this->query['where'], (array)$where);
		
		// Reserved param keys, otherwise intended as where args.
		$reserved = array(
			'where', 'order', 'limit', 'page', 'fields', 'search', 'window', 'offset'
		);
		foreach ((array)$params as $key => $val)
		{
			// Any key not reserved for params.
			if (!in_array($key, $reserved))
			{
				$where[$key] = $val;
			}
		}
		
		// Explicit where param.
		foreach ((array)$params['where'] as $key => $val)
		{
			$where[$key] = $val;
		}
		
		return $where;
	}
	
	/**
	 * Prepare params for db.
	 */
	function prepare_params ($params)
	{
		$params = array_merge((array)$this->query, (array)$params);	
		
		return $params;
	}
	
	/**
	 * Find many records.
	 */
	function find ($where = null, $params = null)
	{
		$params = $this->prepare_params($params);
		$where = $this->prepare_where($where, $params);
		
		$where = $this->trigger('find', $where, $params);
		
		$results = $this->db->find($where, $params);
		
		foreach ((array)$results as $key => $result)
		{
			$results[$key] = $this->trigger('after:find', $result, $where, $params);
		}
		
		return $this->get_model_collection($results);
	}
	
	/**
	 * Find a single record.
	 */
	function find_one ($where = null, $params = null)
	{
		$params = $this->prepare_params($params);
		$where = $this->prepare_where($where, $params);
		
		$where = $this->trigger('find', $where, $params);
		
		$results = $this->db->find($where, $params);
		
		$result = $this->trigger('after:find', array_shift($results), $where, $params);
		
		return $this->get_model_record($result);
	}
	
	/**
	 * Count records.
	 */
	function count ($where = null, $params = null)
	{
		$params = $this->prepare_params($params);
		$where = $this->prepare_where($where, $params);
		
		$where = $this->trigger('count', $where, $params);
		
		$result = $this->db->count($where, $params);
		
		return $this->trigger('after:count', $result, $where, $params);
	}
	
	/**
	 * Paginate through rows of a table.
	 */
	public function paginate ($where = null, $params = null)
	{
		// Shortcut to "all results".
		if ($params['page'] == 'all')
		{
			$params['limit'] = null;
		}
		
		// Defaults.
		$params['page'] = (is_numeric($params['page'])) ? $params['page'] : 1;
		
		if (isset($params['limit']))
		{
			$params['limit'] = (is_numeric($params['limit'])) ? $params['limit'] : null;
		}
		if (isset($params['window']))
		{
			$params['window'] = (is_numeric($params['window'])) ? $params['window'] : null;
		}

		// Prep params.
		$params = $this->prepare_params($params);
		
		// Number of results.
		$result_count = $this->count($where, $params);

		// Page ranges.
		if ($params['limit'])
		{
			$page_count = ceil($result_count / $params['limit']);

			$pages = array();
			$params['window'] = $params['window'] ?: $page_count;
			$min_page = ($min = $params['page'] - $params['window']) < 1 ? 1 : $min;
			$max_page = ($max = $params['page'] + $params['window']) > $page_count ? $page_count : $max;
			for ($i = $min_page; $i <= $max_page; $i++)
			{
				$pages[$i]['start'] = ((($i - 1) * $params['limit']) + 1);
				$pages[$i]['end'] = (($params['limit'] * $i > $result_count) ? $result_count : $params['limit'] * $i);
			}
		}
		
		$page_results = $this->find($where, $params);
		
		return $this->get_model_collection($page_results, array(
			'current' => $params['page'],
			'count' => $result_count,
			'pages' => $pages
		));
	}
	
	/**
	 * Get record wrapper.
	 */
	function get_model_record ($result)
	{
		// One record.
		if (is_array($result))
		{
			return new ModelRecord($result, $this->name);
		}
		
		return null;
	}
	
	/**
	 * Get collection wrapper.
	 */
	function get_model_collection ($results, $meta = null)
	{
		if (($results instanceof ModelCollection) || is_array($results) && count($results) > 0)
		{
			return new ModelCollection($results, $meta, $this->name);
		}
		
		return array();
	}
	
	/**
	 * Magic methods i.e. "find_by_field".
	 */
	function __call ($method, $arguments)
	{
		// Find [one] by ...
		if (preg_match('/^find\_(one\_)?by\_(.*)$/', $method, $match))
		{
			$field_matches = explode('_and_', $match[2]);
			$where = $arguments[count($field_matches)];
			$params = $arguments[count($field_matches)+1];
			
			foreach ($field_matches as $key => $field)
			{
				$field_name = ($this->has_field("{$field}_{$this->pk}")) ? "{$field}_{$this->pk}" : $field;
				$where = array_merge(array("{$field_name}" => $arguments[$key]), (array)$where);
			}

			return ($match[1] == 'one_') ? $this->find_one($where, $params) : $this->find($where, $params);
		}

		// Count by ...
		if (preg_match('/^count\_by\_(.*)$/', $method, $match))
		{
			$field_matches = explode('_and_', $match[1]);
			$where = $arguments[count($field_matches)];
			
			foreach ($field_matches as $key => $field)
			{
				$field_name = ($this->has_field("{$field}_{$this->pk}")) ? "{$field}_{$this->pk}" : $field;
				$where = array_merge(array("{$field_name}" => $arguments[$key]), (array)$where);
			}

			return $this->count($where);
		}

		// Paginate by ...
		if (preg_match('/^paginate\_by\_(.*)$/', $method, $match))
		{
			$field = $match[1];
			$field_name = ($this->has_field("{$field}_{$this->pk}")) ? "{$field}_{$this->pk}" : $field;
			$where = array_merge(array("{$field_name}" => $arguments[0]), (array)$arguments[1]);
			$params = $arguments[2];

			return $this->paginate($where, $params);
		}

		throw new Exception('Undefined method '.$method);
	}
	
	/**
	 * Validates fields based on rules from the class variable $validate.
	 *
	 * @param {array} $values Array containing field values indexed by field name
	 * @param {string} $action Action to validate against
	 * @return {bool} True when all fields validate correctly, false otherwise
	 */
	function validate (&$all_values, $action = null, $rules = null)
	{
		if ($all_values)
		{
			if (!is_array($rules))
			{
				$rules = $this->validate;
			}

			if (!is_int(key($all_values)))
			{
				$all_values = array($all_values);
				$value_is_keyed = false;
			}

			foreach ($all_values as $value_key => $values)
			{
				$all_values[$value_key] = $this->trigger('validate', $values, $action, $rules);
				
				foreach ((array)$rules as $rule => $params)
				{
					if (is_numeric($rule) && is_string($params))
					{
						$rule = $params;
					}
					
					$value_key_if_keyed = ($value_is_keyed !== false) ? $value_key : null;
					
					// Meta rules.
					if ($rule[0] == ':')
					{
						$meta_field = substr($rule, 1);
						$meta_values = $all_values[$value_key][$meta_field];
						
						if (is_array($meta_values))
						{
							foreach ($meta_values as $key => $val)
							{
								foreach ((array)$params as $rule => $p)
								{
									$all_values[$value_key][$meta_field][$key] = $this->validate_rule($rule, $p, $all_values[$value_key][$meta_field][$key], $action);
								}
							}
						}
					}
					else
					{
						$all_values[$value_key] = $this->validate_rule($rule, $params, $all_values[$value_key], $action, $value_key_if_keyed);
					}
				}
			}

			if ($value_is_keyed === false)
			{
				$all_values = $all_values[0];
			}
		}
		
		return !$this->errors_exist();
	}

	/**
	 * Validates values with a single rule.
	 *
	 * @param {string} $rule Rule of validation to perform
	 * @param {string} $params Parameters to validate with
	 * @param {array} $values Values to validate
	 * @param {string} $action Action to validate against
	 */
	function validate_rule ($rule, $params, $values, $action = null, $value_key = null)
	{
		$field_names = array();
		
		if (!is_array($params))
		{
			$params = array($params);
		}

		$default_options = array();
		foreach ($params as $key => $value)
		{
			if (!is_string($key) && is_array($value))
			{
				$default_options = $value;
				unset($params[$key]);
			}
		}

		foreach ($params as $key => $value)
		{
			$field = (is_string($key)) ? $key : $value;

			if ($value_key !== null || ($value_key === null && !$this->errors[$field]))
			{
				$options = (is_array($value)) ? $value : $default_options;

				if (!isset($values[$field]) && $options['not_null'] == true)
				{
					$values[$field] = '';
				}
				
				if ($values[$field] !== null)
				{
					if ($action)
					{
						if ($options['on'])
						{
							$options['on'] = (is_array($options['on'])) ? $options['on'] : array($options['on']);
							if (!in_array($action, $options['on']))
							{
								continue;
							}
						}
						
						if ($options['not_on'])
						{
							$options['not_on'] = (is_array($options['not_on'])) ? $options['not_on'] : array($options['not_on']);
							if (in_array($action, $options['not_on']))
							{
								continue;
							}
						}
					}
					
					$options = array_merge($options, array(
						'action' => $action,
						'values' => $values,
						'value_key' => $value_key
					));
					
					$values[$field] = $this->trigger("validate:{$rule}", $values[$field], $field, $options, $this);
				}
			}
		}
		
		return $values;
	}

	/**
	 * Throw an error, with an optional field and key.
	 */
	function error ($error, $field = null, $key = null)
	{
		$error = $this->trigger('error', $error, $field, $key);
		
		if (!empty($field))
		{
			if ($key !== null)
			{
				$this->errors[$field][$key] = $error;
			}
			else
			{
				$this->errors[$field] = $error;
			}
		}
		else
		{
			$this->errors[] = $error;
		}

		return;
	}
	
	/**
	 * Returns true if model has a specific field.
	 */
	public function has_field ($name, $static = null)
	{
		if ($static === true)
		{
			return (
				empty($this->fields) ||
				in_array($name, (array)$this->fields)
			) ? true : false;
		}
		elseif ($static === false)
		{
			return (
				isset($this->fields[$name]) &&
				!is_scalar($this->fields[$name]) &&
				is_callable($this->fields[$name])
			) ? true : false;
		}

		return (
			empty($this->fields) ||
			isset($this->fields[$name]) ||
			in_array($name, (array)$this->fields)
		) ? true : false;
	}

	/**
	 * Returns true if errors exist in this model.
	 */
	public function errors_exist ()
	{
		return count($this->get_errors()) ? true : false;
	}

	/**
	 * Returns errors in this model.
	 */
	public function get_errors ()
	{
		return $this->errors;
	}

	/**
	 * Clear all existing errors in this model.
	 */
	public function clear_errors ()
	{
		$this->errors = array();
	}
}

