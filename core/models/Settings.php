<?php
/**
 * Setting model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Settings extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{
		// Fields.
		$this->fields = array(
			'id',
			'slug',
			'name',
			'entries',
			'date_created',
			'date_updated'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'slug' => 'unique'
		);
		
		// Get settings.
		$this->bind('GET, GET.*', function ($event)
		{
			array_unshift($event['stack'], $event['id']);
			$setting = Settings::get_setting($event['stack']);
			
			return $setting ? new ModelRecord($setting, 'settings') : null;
		});
	}
	
	/**
	* Get a setting value.
	*/
	function get_setting ($stack)
	{
		// @TODO: pull this from a collection later.
		$config = Request::$config;
		$result = $config->settings;
		
		foreach ((array)$stack as $key => $val)
		{
			if (is_array($result) && isset($result[$val]))
			{
				$result = $result[$val];
			}
			else
			{
				return null;
			}
		}
		
		return $result;
	}
}