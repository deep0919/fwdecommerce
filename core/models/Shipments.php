<?php
/**
 * Shipment model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Shipments extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{	
		// Fields.
		$this->fields = array(
			'id',
			'order_id',
			'name',
			'phone',
			'address',
			'city',
			'state',
			'zip',
			'country',
			'method',
			'tracking',
			'items',
			'date_created',
			'date_updated',
			// @TODO: Auto collections for: shipped items, not yet shipped items, replaced items
			'items' => function ($shipment)
			{
				return get("/products", array(':with' => $shipment['items']));
			},
			'order' => function ($shipment)
			{
				return get("/orders/{$shipment['order_id']}");
			},
			'carrier' => function ($shipment)
			{
				return Shipments::get_carrier($shipment);
			}
		);
		$this->search_fields = array(
			'name',
			'address'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'order_id',
				'name',
				'address',
				'city',
				'state',
				'method',
				'tracking',
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
		
			// Get shipping methods according to params.
			'GET' => function ($event, $model)
			{
				$params =& $event['data'];
				
				// Get shipping methods?
				if ($event['id'] == "methods")
				{
					$methods = array();
					
					// Trigger special context event.
					$methods = trigger('shipments', 'methods', $methods, $params, $model);
					
					// Process default shipping methods?
					if (empty($methods))
					{
						$methods = Shipments::process_default_methods($methods, $params);
					}
					
					// No methods exist?
					if (!empty($methods))
					{
						// Order methods by price.
						if ($params)
						{
							$methods = orderby($methods, 'price');
						}
						
						// Direct result.
						return array('result' => $methods);
					}
					
					return false;
				}
			},
			
			// POST shipment.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				if ($data['order_id'] && $order = get("/orders/{$data['order_id']}"))
				{
					// Default data from order.
					$data = array_merge((array)$order['shipping'], $data);
					$data['name'] = $data['name'] ?: $order['name'];
					$data['phone'] = $data['phone'] ?: $order['phone'];
					
					// Filter shipment items.
					$shipment_items = array();
					foreach ($order['items'] as $order_item_id => $item)
					{
						// Specific items? Otherwise defaults to all order items.
						if (is_array($data['items']))
						{
							$add_this_item = false;
							foreach ($data['items'] as $data_item)
							{
								if (is_array($data_item) && $data_item['order_item_id'] == $order_item_id)
								{
									$add_this_item = true;
								}
							}
							if ($add_this_item === false)
							{
								continue;
							}
						}
						
						// Bundle?
						if ($item['items'])
						{
							foreach ($item['items'] as $i)
							{
								$shipment_items[++$ship_item_id] = array(
									'id' => $i['id'],
									'quantity' => $i['quantity']*$item['quantity'],
									'order_item_id' => $order_item_id
								);
							}
						}
						else
						{
							$shipment_items[++$ship_item_id] = array(
								'id' => $item['id'],
								'quantity' => $item['quantity'],
								'order_item_id' => $order_item_id
							);
						}
					}
					
					// Filtered.
					$data['items'] = $shipment_items;
				}
			},
			
			// PUT, POST shipment.
			'PUT, POST' => function ($event, $model)
			{
				$data =& $event['data'];
				
				if ($data['items'])
				{
					// Clean items data if ID missing.
					foreach ((array)$data['items'] as $id => $item)
					{
						if (!$item['id'] || !$item['quantity'])
						{
							unset($data['items'][$id]);
						}
					}
					
					// Shipment requires at least 1 item.
					if (count($data['items']) == 0)
					{
						$model->error('Must contain at least one item', 'items');
					}
				}
			},
			
			// After POST, PUT.
			'after:POST, after:PUT' => function ($result, $event)
			{
				$data =& $event['data'];
				
				if ($result)
				{
					// Send shipment e-mail?
					if ($data[':email'])
					{
						$settings = get("/settings/emails/shipment");
						
						if ($settings !== false)
						{
							$settings['shipment'] = $result;
							$settings['to'] = $result['order']['account']['email'];
							
							// Default subject?
							if ($settings['subject'])
							{
								$settings['subject'] .= ' #'.$result['order']['id'];
							}
							
							// Override default email?
							if (is_array($data[':email']))
							{
								$settings = array_merge($settings, $data[':email']);
							}
							
							post("/emails/shipment", $settings);
						}
					}
					
					if ($result['order_id'])
					{
						// Set date_shipped on order?
						if (!$result['order']['date_shipped'])
						{
							put("/orders/{$result['order_id']}", array(
								'date_shipped' => $result['date_created']
							));
						}
						// Cancelled shipment, clear date_shipped?
						else if ($result['is_cancelled'])
						{
							if (count($result['order']['shipments']) == 1)
							{
								put("/orders/{$result['order_id']}", array(
									'date_shipped' => null
								));
							}
						}
					}
				}
			},
			
			// After DELETE.
			'after:DELETE' => function ($result, $event)
			{
				if ($result)
				{
					// Last shipment of an order? Unset date shipped.
					if ($result['order']['shipments']['count'] == 0)
					{
						put("/orders/{$result['order_id']}", array(
							'date_shipped' => null
						));
					}
				}
			}
		);
	}
	
	/**
	 * Process default shipment methods.
	 */
	static function process_default_methods ($methods = null, $params = null)
	{
		$settings = get("/settings/shipments/default");
		
		// Default methods enabled?
		if (!$settings['methods'] || $settings['enabled'] === false)
		{
			return $methods;
		}
		
		// Default weight (for price ratio).
		$weight = $params['weight'] ?: 1;
		
		// Append default methods?
		foreach ((array)$settings['methods'] as $key => $method)
		{
			// Price by weight ratio?
			if ($method['price'] && $settings['price_weight_ratio'])
			{
				$method['price'] = $weight * $method['price'] * $settings['price_weight_ratio'];
			}
			
			$methods[$key] = $method;
		}
		
		return $methods;
	}
	
	/**
	 * Get shipment carrier.
	 */
	static function get_carrier ($shipment)
	{
		if ($shipment['carrier'])
		{
			return $shipment['carrier'];
		}
		
		// Try to determine carrier by method name.
		if ($shipment['method'][1] == 'P')
		{
			return 'UPS';
		}
		else if ($shipment['method'][0] == 'F')
		{
			return 'FedEx';
		}
		else if ($shipment['method'][0] == 'U')
		{
			return 'USPS';
		}
		
		return 'Unknown';
	}
}
