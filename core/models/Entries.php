<?php
/**
 * Entry model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Entries extends AppModel
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
			'channel_id',
			'date_created',
			'date_updated',
			'channel' => function ($entry)
			{
				return get("/channels/{$entry['channel_id']}");
			},
			'meta' => function ($entry, $model)
			{
				return $model->get_meta($entry);
			}
		);
		$this->search_fields = array(
			'slug',
			'name',
			'title',
			'content'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'slug, channel_id' => 'unique'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'slug',
				'channel_id'
			),
			'unique' => array(
				'slug'
			)
		);
		
		// Query defaults.
		$this->query = array(
			'order' => 'date_created DESC',
			'limit' => 100
		);
		
		// Event binds.
		$this->binds = array(
		
			// GET entry.
			'GET' => function ($event)
			{
				$data =& $event['data'];
				
				// Get by channel ID or slug.
				if ($data['channel'])
				{
					if ($channel = get("/channels/{$data['channel']}"))
					{
						$data['channel_id'] = $channel['id'];
						unset($data['channel']);
					}
					else
					{
						// Channel not found.
						return false;
					}
				}
			},
		
			// POST entry.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Default slug.
				if (empty($data['slug']))
				{
					if (isset($data['slug']) && ($name = $data['name'] ?: $data['title']))
					{
						$data['slug'] = hyphenate($name);
					}
					else
					{
						$data['slug'] = $data['id'] ?: md5(microtime());
					}
				}
			},
			
			// PUT entry.
			'PUT' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Version this entry?
				if ($data[':version'] && $entry = get("/entries/{$event['id']}"))
				{
					if ($data[':version'] != $entry['date_updated'])
					{
						$ver_by = $data['version_by'];
						$conflicts = $entry['version_conflicts'];
						
						// Check diff with channel fields.
						foreach ((array)$entry['channel']['fields'] as $field)
						{
							$field_id = $field['id'];
							
							if (isset($data[$field_id]) && isset($entry[$field_id]) && $data[$field_id] != $entry[$field_id])
							{
								$conflicts[$ver_by][$field_id]['yours'] = $data[$field_id];
								$conflicts[$ver_by][$field_id]['theirs'] = $entry[$field_id];
							}
						}
						
						// Used to resolve stacking conflicts.
						$conflicts[$ver_by]['version_by']['theirs'] = $entry['version_by'];
						$conflicts[$ver_by]['date_updated']['theirs'] = $entry['date_updated'];
						
						if ($conflicts)
						{
							put($entry, array(
								'version_conflicts' => $conflicts,
								'date_updated' => $entry['date_updated']
							));
							return false;
						}
					}
				}
			},
			
			// PUT, POST entry.
			'PUT, POST' => function ($event)
			{
				$data =& $event['data'];
			
				// Set entry channel?
				if (isset($data['channel']))
				{
					$channel = get("/channels/{$data['channel']}");
					$data['channel_id'] = $channel['id'];
					unset($data['channel']);
				}
			}
		);
	}
	
	/**
	* Get meta fields (not included in fields).
	*/
	function get_meta ($entry)
	{
		$meta = array();
		
		foreach ((array)$entry as $key => $val)
		{
			if (!$this->has_field($key))
			{
				$meta[$key] = $val;
			}
		}
		
		return $meta;
	}
}
