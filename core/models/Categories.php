<?php
/**
 * Category model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Categories extends AppModel
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
			'parent_id',
			'description',
			'date_created',
			'date_updated',
			'parent' => function ($category)
			{
				return $category['parent_id'] ? get("/categories/{$category['parent_id']}") : null;
			},
			'children' => function ($category)
			{
				return get("/categories", array(
					'parent_id' => $category['id'],
					'limit' => null
				));
			},
			'child_count' => function ($category)
			{
				return get("/categories/:count", array(
					'parent_id' => $category['id']
				));
			},
			'products' => function ($category)
			{
				return get("/products", array(
					'category_ids' => $category['id'],
					'limit' => null
				));
			},
			'product_count' => function ($category)
			{
				return get("/products/:count", array(
					'category_ids' => $category['id']
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
		
		// Query options.
		$this->query = array(
			'order' => 'parent_id ASC, name ASC'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'slug',
				'name'
			),
			'unique' => array(
				'slug'
			)
		);
		
		// Event binds.
		$this->binds = array(
			
			// POST category.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Auto slug?
				if ($data['name'] && !$data['slug'])
				{
					$data['slug'] = hyphenate($data['name']);
				
					// Append parent slug?
					if ($parent = get("/categories/{$data['parent_id']}"))
					{
						$data['slug'] = $parent['slug'].'-'.$data['slug'];
					}
				}
			},
			
			// PUT, POST category.
			'PUT, POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Set parent id?
				if ($data['parent'])
				{
					$parent = get("/categories/{$data['parent']}");
					$data['parent_id'] = $parent['id'] ?: $data['parent_id'];
					unset($data['parent']);
				}
			}
		);
	}
}