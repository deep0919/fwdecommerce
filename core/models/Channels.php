<?php
/**
 * Channel model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Channels extends AppModel
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
			'fields',
			'inputs',
			'subscribers',
			'date_created',
			'date_updated',
			'fields' => function ($channel)
			{
				return orderby($channel['fields'], 'sort');
			},
			'fields_by_id' => function ($channel)
			{
				return Channels::get_fields_by_id($channel);
			},
			'entries' => function ($channel)
			{
				return get("/entries", array(
					'channel_id' => $channel['id']
				));
			},
			'entry_count' => function ($channel)
			{
				return get("/entries/:count", array(
					'channel_id' => $channel['id']
				));
			}
		);
		$this->search_fields = array(
			'name'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'slug' => 'unique'
		);
		
		// Query defaults.
		$this->query = array(
			'order' => 'name ASC'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'name',
				'slug'
			),
			'unique' => array(
				'slug'
			),
			':fields' => array(
				'required' => array(
					'id',
					'name',
					'type'
				),
				'unique' => array(
					'id'
				)
			)
		);
		
		// Event binds.
		$this->binds = array(
			
			// POST channel.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Auto slug?
				if ($data['name'] && !$data['slug'])
				{
					$data['slug'] = hyphenate($data['name']);
				}
			},
			
			// POST fields.
			'POST.fields' => function ($event)
			{
				$data =& $event['data'];
				
				// Default field ID to underscored field name.
				if (isset($data['name']) && !$data['id'])
				{
					$data['id'] = underscore($data['name']);
				}
			}
		);
	}
	
	/**
	* Get channel fields by field id.
	*/
	function get_fields_by_id ($channel)
	{
		$fields_by_id = array();
		
		foreach ((array)$channel['fields'] as $field)
		{
			$fields_by_id[$field['id']] = $field;
		}
		
		return $fields_by_id;
	}
}