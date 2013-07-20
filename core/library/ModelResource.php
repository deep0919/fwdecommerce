<?php
/**
 * ModelResource.
 * Provies an array-like interface to model resources.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class ModelResource extends ArrayInterface
{
	protected $uri;
	protected $model_name;
	
	/**
	 * Constructor.
	 */
	function __construct ($values, $model_name)
	{
		$this->model_name = $model_name;
		
		$uri = "/".hyphenate($model_name);
		
		if ($this->model())
		{
			$uri .= ($pk = $values[$this->model()->pk]) ? "/{$pk}" : '';
		}
		
		$this->uri($uri);
		
		parent::__construct($values);
	}
	
	/**
	 * Get or set resource indicator.
	 */
	function uri ($value = null)
	{
		$this->uri = $value ?: $this->uri;
		
		return $this->uri;
	}
	
	/**
	 * Return the model name.
	 */
	function name ()
	{
		return $this->model_name;
	}
	
	/**
	 * Return the model instance.
	 */
	function model ()
	{
		return Request::$controller->{$this->model_name};
	}
	
	/**
	 * Convert instance to a string, represented by a uri.
	 */
	function __toString ()
	{
		return (string)$this->uri;
	}
}
