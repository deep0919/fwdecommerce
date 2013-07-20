<?php
/**
 * Trash model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Trash extends AppModel
{
	// Make this easy to identify.
	public $trash = true;
	
	// Identify by unique slug.
	public $pk = 'deleted_uri';
	public $slug_pk = 'deleted_uri';
	
	/**
	 * Model definition.
	 */
	function define ()
	{
		// Index only one copy in the trash.
		$this->indexes = array(
			'deleted_uri' => 'unique'
		);
		
		$this->query = array(
			'order' => 'date_deleted DESC'
		);
		
		$this->binds = array(
		
			// PUT anything to trash.
			'PUT.*' => function ($event, $model)
			{
				$record =& $event['data'];
				
				// Restore from trash?
				if ($record[':restore'])
				{
					$id = "/{$event['id']}/{$event['stack'][0]}";
					
					if ($trashed = $model->get($id))
					{
						$restore_uri = $trashed['deleted_uri'];
						
						unset($trashed['is_deleted']);
						unset($trashed['deleted_uri']);
						unset($trashed['date_deleted']);
						unset($trashed['_date_deleted']);
						
						// Put it back where it was.
						$result = put($restore_uri, $trashed);
						
						// Verify it exists where it was.
						if ($restored = get($restore_uri))
						{
							// Just in case!
							if (!isset($restored['is_deleted']))
							{
								$model->delete($id);
								return $restored;
							}
						}
						
						// Had errors restoring?
						foreach ((array)$result['errors'] as $field => $error)
						{
							$model->error($error, $field);
						}
					}
				}
				// Put in trash?
				else
				{
					$uri = "/{$event['id']}/{$event['stack'][0]}";
					
					if (!isset($record['is_deleted']))
					{
						$record['is_deleted'] = true;
						$record['date_deleted'] = time();
						
						// Use model to break stack from event.
						$result = $model->put($uri, $record);
						
						// Verify it exists in trash.
						if ($model->get($uri))
						{
							return $result;
						}
					}
					else
					{
						return true;
					}
					
					throw new Exception("Unable to trash {$id}");
				}
			},
			
			// Get something from the trash.
			'GET.*' => function ($event, $model)
			{
				// Model/id. 
				$id = "/{$event['id']}/{$event['stack'][0]}";
				
				return $model->get($id);
			},
			
			// Disable single GET by single ID.
			'GET' => function ($event)
			{
				if ($event['id'] && strpos($event['id'], '/') === false)
				{
					return false;
				}
			}
		);
	}
}