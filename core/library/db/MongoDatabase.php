<?php

/**
 * Mongo database class.
 */
class MongoDatabase extends Database
{
	// Local collection properties.
	public $collection;
	public $fields;
	public $search_fields;
	public $database;
	public $pk;
	public $indexes;
	public $auto_increment;
	public $errors;
	
	// Reference to database handle.
	public $dbh;

	// Default db params.
	public $params = array(
		'user' => null,
		'pass' => null,
		'host' => 'localhost',
		'port' => '27017',
		'database' => 'default',
		'debug' => false
	);

	// Static properties.
	static $mongo;
	static $db_params;

	/**
	 * Construct mongo.
	 */
	function __construct ($params)
	{
		parent::__construct($params);
		
		// Adapter represents one collection at a time.
		if ($params['name'])
		{
			$this->collection = underscore($params['name']);
		}
		else
		{
			throw new Exception("MongoDB adapter requires param 'name'");
		}
		
		// Connect!
		$this->connect();
	}

	/**
	 * Connect to the Mongo database.
	 */
	function connect ()
	{
		/*
		Parse config->mongo for connection params.
			
			example yml:
			  mongo:
			    [{database}:]
			      user: {username}
			      pass: {password}
			      [database: {database}]
			      [host: {host}]
			      [debug: {true|false|1|2}]
			      [log: {true|false}]
			    ...
		*/
		if (!self::$db_params)
		{
			self::$db_params = array();
			$default_params = array();
			foreach ((array)Request::$config->mongo as $key => $value)
			{
				if (is_array($value) && $value['user'] && $value['pass'] && $value['database'])
				{
					self::$db_params[$key] = $value;
				}
				else
				{
					$default_params[$key] = $value;
				}
			}
			if (!empty($default_params))
			{
				self::$db_params['default'] = $default_params;
			}
		}
		
		// Select connection params by 'database' key.
		$this->params = array_merge(
			(array)$this->params,
			(array)($this->database ? self::$db_params[$this->database] : self::$db_params['default'])
		);
		
		// Remember chosen database.
		$this->database = $this->params['database'];
		
		// Re-usable database handle.
		$this->dbh =& self::$mongo[$this->database];
		
		// Need to setup mongo instance?
		if(($this->dbh instanceof MongoDB) == false)
		{
			// Default params.
			$host = $this->params['host'] ?: 'localhost';
			$port = $this->params['port'] ?: 27017;
			$options = $this->params['options'] ?: array();
			
			// Default database name.
			$this->database = $this->database ?: 'default';
			
			// Username and password?
			if ($this->params['user'] && $this->params['pass'])
			{
				$userpass = "{$this->params['user']}:{$this->params['pass']}@";
				
				// Hide password.
				unset($this->params['pass']);
			}
			
			// Connect to Mongo.
			$mongo_class = class_exists('MongoClient') ? 'MongoClient' : 'Mongo';
			$mongo = new $mongo_class("mongodb://{$userpass}{$host}:{$port}/{$this->database}", $options);
			
			// Get the database handle.
			$this->dbh = $mongo->selectDB($this->database);
		}
		
		// Shortcut to the mongo collection.
		$this->dbc = $this->dbh->{$this->collection};
		
		// Ensure indexes.
		$this->ensure_indexes($this->indexes);
	}

	/**
	 * Insert a record.
	 */
	function insert ($values)
	{
		// Auto increment field?
		if ($this->auto_increment && !$values[$this->auto_increment])
		{
			$result = $this->dbh->command(array(
				'findandmodify' => 'auto_increments',
				'query' => array('_id' => $this->collection),
				'update' => array('$inc' => array("{$this->auto_increment}" => 1)),
				'upsert' => true,
				'new' => true
			));
			
			// Is it lower than start value? Then increment to start.
			if ($result['value'][$this->auto_increment] < $this->auto_increment_start)
			{
				$result = $this->dbh->command(array(
					'findandmodify' => 'auto_increments',
					'query' => array('_id' => $this->collection),
					'update' => array('$inc' => array("{$this->auto_increment}" => $this->auto_increment_start-1)),
					'upsert' => true,
					'new' => true
				));
			}
			
			// Prepend auto increment field.
			$values = array("{$this->auto_increment}" => $result['value'][$this->auto_increment]) + $values;
		}
			
		// Prepare. Doc must be set.
		if (!$doc = $this->prepare_doc($values))
		{
			return false;
		}
		
		// Insert options.
		$options = array(
			'safe' => true
			// fsync?
			// timeout?
		);
		
		try // to insert the doc.
		{
			$result = $this->dbc->insert($doc, $options);
			
			if (!$result['err'])
			{
				$id = $values[$this->pk] ?: $result['upsert'];
			}
		}
		// Failed to insert?
		catch (Exception $e)
		{
			$this->error('Insert failed', array(
				'values' => $doc,
				'options' => $options
			));
			return false;
		}
	
		return $id;
	}

	/**
	 * Delete a record.
	 */
	function delete ($where)
	{
		if (empty($where))
		{
			return false;
		}
		
		// Prepare.
		$where = $this->prepare_where($where);
		
		// Remove options.
		$options = array(
			'safe' => true,
			'justOne' => true
			// fsync?
			// timeout?
		);
		
		try { // to remove it.
			$result = $this->dbc->remove((array)$where, $options);
		}
		catch (Exception $e)
		{
			$this->error('Delete failed', array(
				'where' => $where,
				'options' => $options
			));
			return false;
		}

		return $result ? 1 : false;
	}

	/**
	 * Update a record.
	 */
	function update ($values, $where, $return = false)
	{
		if (empty($where))
		{
			return false;
		}
		
		// Primary key found in values.
		if (isset($values[$this->pk]))
		{
			$where[$this->pk] = $values[$this->pk];
			unset($values[$this->pk]);
		}

		// Prepare.
		$doc = $this->prepare_doc($values);
		$where = $this->prepare_values($where);
		
		// Document and where must be set.
		if (!$doc || !$where)
		{
			return false;
		}
		
		// Update options.
		$options = array(
			'upsert' => false,
			'multiple' => false,
			'safe' => true,
			// fsync?
			// timeout?
		);
		
		// Mongo _id not allowed in update.
		unset($doc['_id']);
		
		try // to update the doc.
		{
			$this->dbc->update($where, array('$set' => $doc), $options);
		}
		catch (Exception $e)
		{
			$this->error('Update failed', array(
				'where' => $where,
				'values' => $doc,
				'options' => $options
			));
			return false;
		}
		
		// Return updated document?
		if ($return)
		{
			return $this->find_one($where);
		}
		
		// Only 1 document update allowed at a time.
		return $this->find_one($where) ? 1 : 0;
	}
	
	/**
	 * Find records.
	 */
	function find ($where = null, $params = null)
	{
		// Prepare.
		$fields = $this->prepare_fields($params['fields']);
		$where = $this->prepare_where($where, $params);
		
		// Find it.
		$cursor = $this->dbc->find((array)$where, (array)$fields);

		// Order by?
		if ($params['order'])
		{
			if (is_string($params['order']))
			{
				$order_fields = preg_split('/[\s]*,[\s]*/', trim($params['order']));
				$params['order'] = array();
				foreach ((array)$order_fields as $of)
				{
					list($field, $dir) = preg_split('/[\s]+/', $of);
					
					// Default ascending.
					$params['order'][$field] = strtolower(trim($dir)) == 'desc' ? -1 : 1;
				}
			}
			
			$cursor->sort($params['order']);
		}
		
		// Limit/offset?
		if ($params['limit'] !== null)
		{
			$start = (($params['page'] - 1) * $params['limit']) + $params['offset'];
			$cursor->limit($params['limit']);
			
			if ($start > 0)
			{
				$cursor->skip($start);
			}
		}
		elseif ($params['offset'] > 0)
		{
			$cursor->skip($params['offset']);
		}
		
		// Build results.
		$results = array();
		foreach ($cursor as $doc)
		{
			// Convert _id to normal string.
			$doc['_id'] = (string)$doc['_id'];
			
			foreach ($doc as $field => $value)
			{
				// Convert MongoDate types to ISO string.
				if ($value instanceof MongoDate)
				{
					$value = $doc[$field] = gmdate("Y-m-d\TH:i:s\Z", $value->sec);
				}
				
				// Un-privatize fields.
				if ($field[0] == '_')
				{
					$pfield = substr($field, 1);
					if (!array_key_exists($pfield, $doc))
					{
						$doc[$pfield] = $value;
					}
				}
			}
			
			// Append to results keyed by primary.
			$results[$doc[$this->pk]] = $doc;
		}

		return $results;
	}
	
	/**
	 * Find one record.
	 */
	function find_one ($where = null, $params = null)
	{
		$results = $this->find($where, $params);
		
		return array_shift($results);
	}

	/**
	 * Count records.
	 */
	function count ($where = null, $params = null)
	{
		// Prepare.
		$where = $this->prepare_where($where, $params);
		
		// Count it.
		$count = $this->dbc->count($where);
		
		if (isset($count['errmsg']))
		{
			$this->error('Count failed', array(
				'where' => $where
			));
		}

		return $count;
	}
	
	/**
	 * Ensure indexes.
	 */
	function ensure_indexes ($indexes)
	{
		static $ensured_indexes;
		
		// Already ensured?
		if ($ensured_indexes[$this->database][$this->collection])
		{
			return true;
		}
		
		// Get existing indexes.
		$ex_indexes = $this->dbc->getIndexInfo();
		
		foreach ((array)$indexes as $key => $index)
		{
			// Ensure index options.
			$options = array(
				'safe' => true,
				'background' => true
				// name?
				// timeout?
			);
			
			// Index with options i.e. unique
			if (is_string($key))
			{
				$fields = $key;
				
				// Unique field?
				if (strpos($index, 'unique') !== false)
				{
					$options['unique'] = 1;
				}
			}
			// Plain index.
			else
			{
				$fields = $index;
			}
			
			// Process single and composite indexes the same way
			$where = array();
			$parts = preg_split('/[\s]*,[\s]*/', $fields);
			foreach ($parts as $key)
			{
				// Default descending.
				$order = (strpos($index, 'asc') === false) ? -1 : 1;
				
				$where[$key] = $order;
			}
			
			// Check and skip if already indexed.
			foreach ((array)$ex_indexes as $ex_index)
			{
				if ($ex_index['key'] == $where)
				{
					continue(2);
				}
			}
			
			try // to ensure the index.
			{
				$this->dbc->ensureIndex($where, $options);
			}
			catch (Exception $e)
			{
				$this->error('Index failed', array(
					'values' => $where,
					'options' => $options
				));
				return false;
			}
		}
		
		$ensured_indexes[$this->database][$this->collection] = true;
		return true;
	}
	
	/**
	 * Prepare input values for queries.
	 */
	function prepare_values ($values)
	{
		if (is_array($values))
		{
			foreach ($values as $key => $val)
			{
				$values[$key] = $this->prepare_values($val);
			}
		}
		elseif (is_numeric($values) && $values[0] != '0')
		{
			// Force numeric values to INT or FLOAT type.
			return ((int)$values == $values) ? (int)$values : (float)$values;
		}
		elseif (preg_match('/Date\((.*)\)/i', $values, $matches))
		{
			// Auto convert Date(...) value to MongoDate.
			$date_time = !is_numeric($matches[1]) ? strtotime($matches[1]) : $matches[1];
			return new MongoDate($date_time);
		}
		elseif ($values === false || $values === "false")
		{
			// False means null or not exists.
			return null;
		}
		elseif ($values === "true")
		{
			return true;
		}
		
		return $values;
	}
	
	/**
	 * Prepare document values for insert or update.
	 */
	function prepare_doc ($values)
	{
		if (!is_array($values))
		{
			return null;
		}

		$doc = array();
		foreach ($values as $name => $value)
		{
			// Exclude header fields.
			if ($name[0] == ':')
			{
				continue;
			}
			
			// Null or not present is preferred to empty string for Mongo queries.
			if ($value === "")
			{
				$value = null;
			}
			
			// Auto-convert date_* values to MongoDate types.
			if (substr($name, 0, 5) == "date_")
			{
				if (($value instanceof MongoDate) == false && !preg_match('/Date\((.*)\)/i', $value))
				{
					$date_time = !is_numeric($value) ? strtotime($value) : $value;
					
					// Convert to MongoDate if date_time is valid.
					if ($date_time && is_numeric($date_time))
					{
						$value = new MongoDate($date_time);
					}
				}
			}
			
			$doc[$name] = $this->prepare_values($value, $name);
		}
				
		return $doc;
	}
	
	/**
	 * Prepare where claus with parameters.
	 */
	function prepare_where ($where, $params = null)
	{
		$where = $this->prepare_values($where);
		
		// Merge with search?
		if ($params['search'])
		{
			$search_where = $this->prepare_search($params['search'], $params);
			$where = array_merge($where, $search_where);
		}
		
		// Filter where.
		foreach ((array)$where as $key => $val)
		{
			// Exclude header fields.
			if ($key[0] == ':')
			{
				unset($where[$key]);
			}
		} 
		
		return $where;
	}
	
	/**
	 * Prepare search params.
	 */
	function prepare_search ($search, $params = null)
	{
		// @TODO: Allow $and, $or combinations.
		
		// Query fields by key?
		if (is_array($search))
		{
			foreach ($search as $field => $value)
			{
				$search_field = $field;
				
				if (count($field_parts = explode('.', $field)) > 1)
				{
					$field = $field_parts[0];
				}
				
				if ($field == $this->pk || $field == $this->slug_pk)
				{
					$where[][$search_field] = $this->prepare_values($value);
				}
				else
				{
					$where[][$search_field] = new MongoRegex($value[0] == '/' ? $value : "/{$value}/i");
				}
			}
		}
		// Query search fields by string?
		else if (is_string($search) && $this->search_fields)
		{
			foreach ((array)$this->search_fields as $field)
			{
				$search_field = $field;
				
				if (count($field_parts = explode('.', $field)) > 1)
				{
					$field = $field_parts[0];
				}
				
				if ($field == $this->pk || $field == $this->slug_pk)
				{
					$where[][$search_field] = $this->prepare_values($search);
				}
				else
				{
					$where[][$search_field] = new MongoRegex($search[0] == '/' ? $search : "/{$search}/i");
				}
			}
		}
		
		return !empty($where) ? array('$or' => $where) : array();
	}
	
	/**
	 * Record or display an error.
	 */
	function error ($message = 'Unknown error', $query = 'Unknown query')
	{
		$last_error = $this->dbh->lastError();
		
		if ($last_error['err'])
		{
			$message = ($last_error['err'] == "unauthorized")
				? "Mongo: Unauthorized for {$this->database}"
				: "Mongo: {$message}: {$last_error['err']} in {$this->collection}".json_encode($query);
		}
		else
		{
			$message = "Mongo: {$message}";
		}
		
		throw new Exception($message);
	}
}
