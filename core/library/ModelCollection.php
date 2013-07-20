<?php
/**
 * ModelCollection.
 * Provides a array-like interface to a collection of records.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class ModelCollection extends ModelResource
{
	protected $meta;
	
	/**
	 * Constructor.
	 */
	function __construct ($results, $meta, $model_name)
	{
		if ($results)
		{
			$records = array();
			foreach ((array)$results as $key => $result)
			{
				if (($result instanceof ModelRecord) == false)
				{
					$result = new ModelRecord($result, $model_name);
				}
				$records[$key] = $result;
			}
			$this->meta = $meta;
			
			parent::__construct($records, $model_name);
		}
	}
	
	/**
	 * Get collection record or meta data.
	 */
	function offsetGet ($index)
	{
		if (isset($this->meta[$index]))
		{
			return $this->meta[$index];
		}
		else
		{
			// Offset by PK.
			$records = $this->records();
			
			if ($record =& $records[$index])
			{
				return $record;
			}
			// Offset by Slug.
			else if ($model = $this->model())
			{
				if ($model->slug_pk)
				{
					foreach ((array)$records as $pk => $val)
					{
						if ($val[$model->slug_pk] === $index)
						{
							$record =& $records[$pk];
							
							return $record;
						}
					}
				}
			}
		}

		return null;
	}
	
	/**
	 * Get raw record values.
	 */
	function records ()
	{
		return $this->getArrayCopy();
	}
	
	/**
	 * Dump raw collection values.
	 */
	function dump ($return = false)
	{
		$dump = $this->meta ?: array();
		foreach ((array)$this->records() as $key => $record)
		{
			foreach ((array)$record as $field => $val)
			{
				$dump['records'][$key][$field] = $val;
			}
		}
		if (!$this->meta)
		{
			$dump = $dump['records'];
		}
		
		return print_r($dump, $return);
	}
}
