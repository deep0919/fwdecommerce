<?php
/**
 * Core database.
 * Abstract class for a database interface.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
abstract class Database
{
	// Insert a record.
	abstract function insert ($values);

	// Delete a record.
	abstract function delete ($where);

	// Update a record.
	abstract function update ($values, $where, $return = false);

	// Find records.
	abstract function find ($where = null, $params = null);

	// Count records.
	abstract function count ($where = null, $params = null);
	
	/**
	 * Default constructor.
	 */
	function __construct ($params)
	{
		$this->params = (array)$params;
		
		// Apply params to object.
		foreach ($this->params as $key => $value)
		{
			$this->{$key} = $value;
		}
		
		// Prepare fields.
		$this->fields = $this->prepare_fields($params['fields']);
	}
	
	/**
	 * Loads and returns a db adapter instance.
	 */
	static function get_adapter ($name, $params)
	{
		// Adapter class name convention.
		$adapter_class = camelize($name).'Database';
		
		// Try to load adapter class.
		if (!class_exists($adapter_class))
		{
			$adapter_file = APP_ROOT."core/library/db/{$adapter_class}.php";
			if (is_file($adapter_file))
			{
				require_once $adapter_file;
			}
		}
		
		// Instantiate the adapter.
		$adapter = new $adapter_class($params);
		
		// Make sure it extends Database.
		if (($adapter instanceof Database) == false)
		{
			throw new Exception("{$name} is not a valid database adapter ({$adapter_class})");
		}
		
		return $adapter;
	}
	
	/**
	 * Prepare field list.
	 */
	static function prepare_fields ($fields)
	{
		if ($fields)
		{
			if (is_string($fields))
			{
				$return_fields = preg_split('/[\s]*,[\s]*/', $fields);
			}
			elseif (is_array($fields))
			{
				foreach ($fields as $key => $val)
				{
					$return_fields[] = is_string($key) ? $key : $val;
				}
			}
		}
		
		return $return_fields ?: array();
	}
}