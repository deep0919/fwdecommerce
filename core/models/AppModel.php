<?php
/**
 * App model.
 * Base class for common app models to extend.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class AppModel extends Model
{
	// Default Mongo db.
	public $adapter = 'mongo';
	
	// Primary key.
	public $pk = 'id';
	
	// String based primary key.
	public $slug_pk = 'slug';
	
	// Auto increment id.
	public $auto_increment = 'id';
	
	// Order by id.
	public $query = array(
		'order' => 'id DESC',
		'limit' => 100
	);
	
	/**
	 * Default model definition.
	 */
	function define_default ()
	{
		// Implement soft delete (exclude trash model).
		if (!$this->trash)
		{
			$this->bind('DELETE', function ($event, $model)
			{
				// Put in trash?
				if ($event['id'])
				{
					if ($record = $model->get($event['id']))
					{
						$trashed = put("/trash".$record->uri(), $record);
					}
				}
				
				// Success?
				return $trashed ? true : false;
			});
			
			$this->bind('PUT', function ($event, $model)
			{
				$data =& $event['data'];
				$collection = underscore($model->name);
				
				// Restore from trash?
				if ($data[':restore'] && $collection && $event['id'])
				{
					return put("/trash/{$collection}/{$event['id']}", array(':restore' => true));
				}

				// Finalize uploaded images.
				if ($data['images'])
				{
					foreach ((array)$data['images'] as $name => $image)
					{
						if (!$name || !$image)
						{
							continue;
						}
						
						// Put uploaded image in proper location.
						// Only works if file was uploaded with this $name and $image path.
						$result = put("/{$collection}/{$event['id']}/images/{$name}", $image);
						
						// Confirm or deny result.
						if ($result)
						{
							// Save result to collection.
							$result['name'] = $name;
							$data['images'][$name] = $result;
						}
						else
						{
							// Problem uploading.
							unset($data['images'][$name]);
						}
					}
					
					// Update/merge images field?
					if (!empty($data['images']))
					{
						$record = $model->get($event['id']);
						$data['images'] = merge($record['images'], $data['images']);
					}
				}
			});
			
			$this->bind('after:GET', function ($result, $event, $model)
			{
				// Not found, check trash?
				if (!$result && $event['id'] && !isset($event['query']['notrash']))
				{
					if ($collection = underscore($model->name))
					{
						if ($result = get("/trash/{$collection}/{$event['id']}", $event['data']))
						{
							// Convert to model record.
							return $model->get_model_record($result->values());
						}
					};
				}
			});
		}
		
		// Upload something (temp location).
		$this->bind('PUT.upload', function ($event, $model)
		{
			// This will upload a file to the config.app.public_path/uploads location (temp).
			if ($upload_file = $model->upload_file($event['id'], $event['upload_id'], $event['data']))
			{
				return new ModelResource(array('file' => $upload_file), $upload_file);
			}
			
			return false;
		});
		// Put uploaded image in proper location, related to model, returns file name.
		$this->bind('PUT.images', function ($event, $model)
		{
			// This will move a previously uploaded image to the image location.
			if ($image_file = $model->upload_image($event['id'], $event['image_id'], $event['data']))
			{
				return new ModelResource(array('src' => $image_file), $image_file);
			}
			
			return false;
		});
		// Put uploaded file content in document. 
		$this->bind('PUT.file', function ($event, $model)
		{
			// This will get the contents of a previously uploaded file and delete it.
			if ($file_content = $model->get_upload_file_content($event['id'], $event['file_id']))
			{
				// Save file content in doc field "file_name".
				return array("file_{$event['file_id']}" => $file_content);
			}
			
			return false;
		});
		// Delete an image.
		$this->bind('DELETE.image', function ($event, $model)
		{
			$model->delete_image($event['id'], $event['image_id']);
			
			return false;
		});
		
		return parent::define_default();
	}

	/**
	* Override insert.
	*/
	function insert ($values)
	{
		// Auto tag date created?
		if ($this->has_field('date_created') && !isset($values['date_created']))
		{
			$values['date_created'] = time();
		}
		
		return parent::insert($values);
	}
	
	/**
	* Override update.
	*/
	function update ($values, $where, $return = false)
	{
		// Auto tag date updated?
		if ($this->has_field('date_updated') && !isset($values['date_updated']))
		{
			$values['date_updated'] = time();
		}
		
		return parent::update($values, $where, $return);
	}
	
	/**
	* Upload a file.
	*/
	function upload_file ($id, $name, $file = null, $options = null)
	{
		$config = Request::$config;
		$type = strtolower($this->name);
		$name = hyphenate($name);
		
		try {
			
			// Get upload image file path.
			if (is_string($file))
			{
				$upload_file = $file;
			}
			else if (is_array($file))
			{
				$upload_file = ($config->app['public_path'].'/uploads')."/{$type}_{$name}_{$file['name']}";
			}
			
			// Upload image to temp location?
			if (is_array($file))
			{
				// File not uploaded?
				if (!$file['tmp_name'])
				{
					return false;
				}

				// Error?
				if ($file['error'])
				{
					throw new Exception($file['error']);
				}
				
				// Supported file types?
				if (is_array($options['allowed_types']) && !in_array($file['type'], $options['allowed_types']))
				{
					throw new Exception("Unsupported file type ({$size['mime']})");
				}
				
				// Acceptable file size? Default 8MB.
				$settings = get("/settings/images");
				$upload_limit = $settings['upload_max_bytes'] ?: 8000;

				if ($file['size'] && ($file['size']/1024 > $upload_limit))
				{
					throw new Exception("File too large (limit {$upload_limit} bytes)");
				}
	
				// Move from tmp location to upload location.
				if (!move_uploaded_file($file['tmp_name'], $upload_file))
				{
					throw new Exception("Unable to move file from '{$file['tmp_name']}' to '{$upload_file}'");
				}
				
				return $upload_file;
			}
			// Return file path?
			else if (is_file($upload_file))
			{
				return $upload_file;
			}
			
			// File must exist by now.
			throw new Exception("File not found '{$upload_file}'");
		}
		catch (Exception $e)
		{
			$this->error($e->getMessage(), 'file');
		}

		return false;
	}
	
	/**
	* Upload an image.
	*/
	function upload_image ($id, $name, $image = null)
	{
		$config = Request::$config;
		$name = hyphenate($name);

		try {
			// Upload image file?
			if (is_array($image))
			{
				return $this->upload_file($id, $name, $image, array(
				
					// Limit types.
					'allowed_types' => array(
						'image/jpeg',
						'image/gif',
						'image/png'
					)
				));
			}
			else
			{
				// Uploaded image exists?
				if ($upload_image_file = $this->upload_file($id, $name, $image))
				{
					$new_image_file = $config->app['public_path'].image(array(
						'id' => $id,
						'name' => $name,
						'type' => strtolower($this->name),
						'if_exists' => false
					));
					
					if (!rename($upload_image_file, $new_image_file))
					{
						throw new Exception("Unable to move image from '{$upload_image_file}' to '{$new_image_file}'");
					}
					
					// Return relative file path.
					return str_replace($config->app['public_path'], '', $new_image_file);
				}
			}
		}
		catch (Exception $e)
		{
			$this->error($e->getMessage(), 'image');
		}
		
		// Show error as image field.
		if ($this->errors['file'])
		{
			$this->error($this->errors['file'], 'image');
		}

		return false;
	}
	
	/**
	* Get uploaded file content and delete it.
	*/
	function get_upload_file_content ($id, $name)
	{
		$name = hyphenate($name);

		try {
			// Get upload image file path.
			$upload_file = $this->upload_file($id, $name);
	
			// Exists?
			if (is_file($upload_file))
			{
				// Get content and delete.
				$file_content = file_get_contents($upload_file);
				
				unlink($upload_file);
				
				return $file_content;
			}
			else
			{
				throw new Exception("File not found '{$upload_file}'");
			}
		}
		catch (Exception $e)
		{
			$this->error($e->getMessage(), 'file');
		}

		return false;
	}
	
	/**
	* Delete an image file from storage.
	*/
	function delete_image ($id, $name)
	{
		$name = hyphenate($name);

		$image_file = $config->app['public_path'].image(array(
			'id' => $id,
			'name' => $name,
			'type' => strtolower($this->name)
		));
		
		if (is_file($image_file))
		{
			return unlink($image_file);
		}
	}
}
