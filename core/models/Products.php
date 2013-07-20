<?php
/**
 * Product model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Products extends AppModel
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
			'price',
			'cost',
			'sku',
			'weight',
			'description',
			'category_ids',
			'variants',
			'items',
			'stock',
			'images',
			'is_bundle',
			'is_active',
			'date_created',
			'date_updated',
			'items' => function ($product)
			{
				return get("/products", array(
					':with' => orderby($product['items'], 'sort')
				));
			},
			'weight' => function ($product)
			{
				return Products::get_weight($product);
			},
			'categories' => function ($product)
			{
				return get("/categories", array(
					'id' => array(
						'$in' => (array)$product['category_ids']
					)
				));
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'name',
			'sku',
			'description'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'slug' => 'unique',
			'sku'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'slug',
				'name',
				'price'
			),
			'unique' => array(
				'slug',
				'sku'
			),
			':items' => array(
				'required' => array(
					'id',
					'quantity'
				)
			)
		);
		
		// Event binds.
		$this->binds = array(
		
			// GET product.
			'GET' => function ($event)
			{
				$data =& $event['data'];
				
				// Get by category ID or slug.
				if ($data['category'])
				{
					if ($category = get("/categories/{$data['category']}"))
					{
						$data['category_ids'] = $category['id'];
						unset($data['category']);
					}
					else
					{
						// Category not found.
						return false;
					}
				}
				
				// Default category sort?
				if (!$data['order'] && is_numeric($data['category_ids']))
				{
					$data['order'] = "category_sort.{$data['category_ids']}";
				}
				
				if ($data['pricing'])
				{
					// Avoid conflicts with actual pricing field.
					$data[':pricing'] = $data['pricing'];
					unset($data['pricing']);
				}
			},
			
			// POST product.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Auto slug?
				if ($data['name'] && !$data['slug'])
				{
					$data['slug'] = hyphenate($data['name']);
				}
			},
			
			// POST, PUT product.
			'POST, PUT' => function ($event, $model)
			{
				$data =& $event['data'];
			
				// Set product categories?
				if (isset($data['categories']))
				{
					foreach ((array)$data['categories'] as $category_id)
					{
						if (!is_numeric($category_id))
						{
							$category = get("/categories/{$category_id}");
							$category_id = $category['id'];
						}
						if ($category_id)
						{
							$data['category_ids'][] = $category_id;
						}
					}
					unset($data['categories']);
				}
				
				// Updating category ids?
				if (is_array($data['category_ids']))
				{
					sort($data['category_ids']);
				}
			},
			
			// POST product variant.
			'POST.variants' => function ($event)
			{
				$data =& $event['data'];
				
				// Unset default override keys
				if (!$data['sku'])
				{
					unset($data['sku']);
				}
				if (!$data['price'])
				{
					unset($data['price']);
				}
			},
		
			// After GET product.
			'after:GET' => function ($result, $event)
			{
				$data =& $event['data'];
				
				if ($result && $data[':pricing'])
				{
					// Handle special pricing.
					$result = Products::get_pricing($result, $data[':pricing']);
				}
			}
		);
	}
	
	/**
	* Get total product weight.
	*/
	function get_weight ($product)
	{
		if ($product['items'])
		{
			foreach ((array)$product['items'] as $item)
			{
				$weight += $item['weight'] * $item['quantity'];
			}
			
			return $weight;
		}
		
		return $product['weight'];
	}
	
	/**
	* Get context based pricing.
	*/
	function get_pricing ($product, $where_pricing)
	{
		$pricing = array();
		
		// Merge account term pricing?
		if ($terms = $where_pricing['terms'])
		{
			if ($term_pricing = $terms['pricing'][$product['id']])
			{
				$tpricing = $product['pricing'];
				foreach ((array)$term_pricing as $key => $val)
				{
					if ($val['price'])
					{
						$tpricing[$key]['price'] = $val['price'];
					}
				}
				
				$product['pricing'] = $tpricing;
			}
		}
							
		// Get pricing by account role?
		foreach ((array)$product['pricing'] as $key => $val)
		{
			$process = true;
			
			if ($val['role'])
			{
				if ((!$where_pricing['roles'] && $val['role']) || ($where_pricing['roles'] && !in_array($val['role'], (array)$where_pricing['roles'])))
				{
					$process = false;
				}
			}
			if ($val['quantity'])
			{
				if ($where_pricing['quantity'] && $where_pricing['quantity'] < $val['quantity'])
				{
					$process = false;
				}
			}
			
			if ($process)
			{
				$pricing[$key] = $val;
			}
		}
		
		// Apply pricing?
		if ($pricing)
		{
			foreach ((array)$pricing as $tier)
			{
				if (isset($tier['price']))
				{
					if (!$product['price'] || $tier['price'] < $product['price'])
					{
						$product['price'] = $tier['price'];
					}
					if (!$product['low_price'] || $tier['price'] < $product['low_price'])
					{
						$product['low_price'] = $tier['price'];
					}
					if (!$product['high_price'] || $tier['price'] > $product['high_price'])
					{
						$product['high_price'] = $tier['price'];
					}
				}
			}
			
			$product['pricing'] = $pricing;
		}
		
		return $product;
	}
}
