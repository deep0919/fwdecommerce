<?php
/**
 * Cart model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Carts extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{
		$this->auto_increment_start = 10000;
		
		// Fields.
		$this->fields = array(
			'id',
			'items',
			'order',
			'coupon_code',
			'discounts',
			'taxes',
			'order_id',
			'account_id',
			'session_id',
			'date_created',
			'date_updated',
			'items' => function ($cart)
			{
				return get("/products", array(':with' => $cart['items']));
			},
			'account' => function ($cart)
			{
				return get("/accounts/{$cart['account_id']}");
			},
			'shipping_methods' => function ($cart)
			{
				return Carts::get_shipping_methods($cart);
			},
			'taxes' => function ($cart)
			{
				return Carts::get_taxes($cart);
			},
			'sub_total' => function ($cart)
			{
				return Carts::get_sub_total($cart);
			},
			'sub_discount' => function ($cart)
			{
				return Carts::get_sub_total($cart, array('discount' => true));
			},
			'shipping_total' => function ($cart)
			{
				return Carts::get_shipping_total($cart);
			},
			'shipping_discount' => function ($cart)
			{
				return Carts::get_shipping_total($cart, array('discount' => true));
			},
			'tax_total' => function ($cart)
			{
				return Carts::get_tax_total($cart);
			},
			'discount_total' => function ($cart)
			{
				return Carts::get_discount_total($cart);
			},
			'grand_total' => function ($cart)
			{
				return Carts::get_grand_total($cart);
			},
			'credit_total' => function ($cart)
			{
				return Carts::get_credit_total($cart);
			},
			'billing_total' => function ($cart)
			{
				return Carts::get_billing_total($cart);
			},
			'product_cost' => function ($cart)
			{
				return Carts::get_sub_total($cart, array('cost' => true));
			},
			'quantity' => function ($cart)
			{
				return Carts::get_quantity($cart);
			},
			'weight' => function ($cart)
			{
				return Carts::get_weight($cart);
			},
			'abandoned' => function ($cart)
			{
				return Carts::abandoned($cart);
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'id',
			'order.name',
			'order.email'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique'
		);
		
		// Validate.
		$this->validate = array(
			'order',
			':items' => array(
				'required' => array(
					'id',
					'price',
					'quantity'
				)
			)
		);
		
		// Cart item quantity limit.
		$this->item_quantity_limit = 99999999;
		
		// Event binds.
		$this->binds = array(
		
			// GET carts.
			'GET' => function ($event)
			{
				$data =& $event['data'];
				
				// Reset cart session?
				if ($data[':reset'])
				{
					$data['items'] = null;
					$data['order'] = null;
					$data['discounts'] = null;
					
					return put("/carts/{$event['id']}", $data);
				}
			},
			
			// POST cart.
			'POST' => function ($event)
			{
				$data =& $event['data'];
				
				// Post item into cart (shortcut to POST.items)
				if ($event['id'] && $data['item'])
				{
					post("/carts/{$event['id']}/items", $data['item']);
					
					return get("/carts/{$event['id']}");
				}
			},
		
			// POST cart items.
			'POST.items' => function ($event)
			{
				$data =& $event['data'];
				
				// Filter the item for update.
				$data = array_pop(Orders::get_items_for_update(array($data)));
				
				if ($cart = get("/carts/{$event['id']}"))
				{
					$product = get("/products/{$data['id']}", array(
						'pricing' => array(
							'roles' => $cart['account']['roles'],
							'quantity' => $data['quantity'] ?: 1
						)
					));
					
					// Item has options?
					if ($data['options'])
					{
						foreach ((array)$data['options'] as $key => $option)
						{
							if (is_scalar($option))
							{
								$data['options'][$key] = $option = array(
									'name' => $option
								);
							}
							
							// Matches product option?
							if (is_array($product['options']) && is_array($product['options'][$key]))
							{
								$data['options'][$key]['name'] = $option['name'] ?: $product['options'][$key]['name'];
								$data['options'][$key]['price'] = $option['price'] ?: $product['options'][$key]['price'];
							}
						}
					}
					
					// Item variant?
					if ($product['variants'])
					{
						$vid = $data['variant_id'];
						if ($variant = $product['variants'][$vid])
						{
							$data['variant'] = $variant;
							$data['price'] = $data['price'] ?: $variant['price'];
						}
						else
						{
							// Variant not found.
							$model->error('not found', 'variant_id');
						}
					}
					else
					{
						$data['variant'] = null;
						$data['variant_id'] = null;
					}
					
					// Prevent two of the same item in cart.
					foreach ((array)$cart['items'] as $key => $item)
					{
						// Exists in cart?
						if ($data['id'] == $item['id']
							&& $data['options'] == $item['options']
							&& $data['variant'] == $item['variant'])
						{
							// Update quantity.
							$item['quantity'] += $data['quantity'];
							return put("{$cart}/items/{$key}", $item);
						}
					}
				}
				
				// Defaults.
				$data['price'] = $data['price'] ?: $product['price'];
				$data['quantity'] = round($data['quantity'] ?: 1);
			},
			
			// PUT cart items.
			'PUT.items' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Defaults.
				if (isset($data['quantity']))
				{
					$data['quantity'] = round($data['quantity'] ?: 1);
					
					// Upper limit on item quantity.
					if ($data['quantity'] > $model->item_quantity_limit)
					{
						$data['quantity'] = $model->item_quantity_limit;
					}
				}
			},
			
			// PUT cart.
			'PUT' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Cart already exists?
				if ($data && $cart = get("/carts/{$event['id']}"))
				{
					// Update items collection?
					if ($data['items'])
					{
						$cart_items = $cart['items'];
						foreach ((array)$data['items'] as $item_id => $item)
						{
							// Update existing item quantity.
							if (isset($item['quantity']) && $cart_items[$item_id])
							{	
								$cart_items[$item_id]['quantity'] = round($item['quantity']);
								
								// Upper limit on item quantity.
								if ($cart_items[$item_id]['quantity'] > $model->item_quantity_limit)
								{
									$cart_items[$item_id]['quantity'] = $model->item_quantity_limit;
								}
								
								// Check product for pricing update?
								$product = get("/products/{$cart_items[$item_id]['id']}", array(
									'pricing' => array(
										'roles' => $cart['account']['roles'],
										'quantity' => $cart_items[$item_id]['quantity'] ?: 1
									)
								));
								
								// Update pricing?
								if ($product['pricing'])
								{
									$cart_items[$item_id]['price'] = $product['price'];
								}
							}
							
							// Remove item?
							if ($data['items'][$item_id]['quantity'] <= 0)
							{
								unset($cart_items[$item_id]);
							}
						}
						
						$data['items'] = $cart_items;
					}
					
					// Removed coupon code?
					if (isset($data['coupon_code']) && !$data['coupon_code'])
					{
						// Remove coupon.
						$data['discounts'] = $cart['discounts'];
						unset($data['discounts']['coupon']);
					}
					// Applied new coupon code?
					elseif ($data['coupon_code'] && $data['coupon_code'] != $cart['discount']['coupon']['code'])
					{
						$discount = get("/discounts", array(
							'code' => $data['coupon_code'],
							'is_valid' => true
						));
						
						if ($discount === false)
						{
							$model->error('already used', 'coupon_code');
						}
						else if (!$discount)
						{
							$model->error('invalid', 'coupon_code');
						}
						
						// Remember code.
						$data['coupon_code'] = $discount['code'];
						
						// Update items from coupon.
						foreach ((array)$discount['rules'] as $rule)
						{
							if ($rule['add'] && $rule['product_id'])
							{
								// Exists in cart?
								$exists = false;
								foreach ((array)$cart['items'] as $item_id => $item)
								{
									if ($item['id'] == $rule['product_id'])
									{
										$exists = $item_id;
										break;
									}
								}
								
								// Update item quantity?
								if ($exists)
								{
									put("{$cart}/items/{$exists}", array(
										'quantity' => $rule['quantity']
									));
								}
								else
								{
									// Post new item to cart.
									post("{$cart}/items", array(
										'id' => $rule['product_id'],
										'quantity' => $rule['quantity']
									));
								}
							}	
						}
						
						$data['discounts'] = $cart['discounts'];
						$data['discounts']['coupon'] = $discount;
					}
					else
					{
						// Don't update coupon code directly.
						unset($data['coupon_code']);
					}
					
					// Extra discount updates.
					if ($data['discounts'])
					{
						foreach ((array)$data['discounts'] as $did => $discount)
						{
							// Unset some discount details.
							unset($data['discounts'][$did]['codes']);
							unset($data['discounts'][$did]['codes_used']);
							unset($data['discounts'][$did]['code_history']);
							unset($data['discounts'][$did]['conditions']);
						}
					}
					
					// Update order data? Merge.
					if (isset($data['order']) && is_array($cart['order']))
					{
						if ($data['order'])
						{
							if ($data['order']['shipping'])
							{
								$data['order']['shipping'] = array_merge((array)$cart['order']['shipping'], (array)$data['order']['shipping']);
							}
							
							$data['order'] = $cart['order'] = array_merge($cart['order'], (array)$data['order']);
						}
					}
					
					// Update shipping total?
					if ($data['order'] || $data['items'])
					{
						// @TODO: Make sure we don't need this.
						//$data['shipping_total'] = Carts::get_shipping_total($cart);
					}
					
					// Use credit?
					if ($data['credit_total'])
					{
						// Validate.
						$data['credit_total'] = Carts::get_credit_total(merge($cart, $data));
					}
				}
			},
			
			// Validate cart order.
			'validate:order' => function ($order, $field, $params, $model)
			{
				// Validate with orders collection.
				$order[':validate'] = true;
				$result = get("/orders", $order);
				
				// Apply errors to cart model?
				foreach ((array)$result['errors'] as $field => $error)
				{
					$model->error($error, 'order', $field);
				}
			}
		);
	}
	
	/**
	* Get cart sub total.
	*/
	function get_sub_total ($cart, $options = null)
	{
		// Add item totals.
		foreach ((array)$cart['items'] as $item)
		{
			$should_add_to_total = 
				(!$item['is_cancelled'] && !$item['is_returned'])
				|| $cart['order']['status'] == 'cancelled'
				|| $cart['order']['status'] == 'returned';
				
			if ($should_add_to_total)
			{
				$sub_total += ($item['price']*$item['quantity']);
				$sub_cost += ($item['cost']*$item['quantity']);
			}
		}
		
		// Return sub cost?
		if ($options['cost'])
		{
			return $sub_cost;
		}
		
		$orig_sub_total = $sub_total;
		
		// Apply discounts.
		$sub_total = Discounts::apply_sub_total($cart, $sub_total, $cart['discounts']);
		
		// Discount total?
		if ($options['discount'])
		{
			return abs($orig_sub_total - total($sub_total));
		}
		
		return $orig_sub_total;
	}
	
	/**
	* @TODO: Move get_*_total() methods into orders.
	* 	Carts always need an order, but an order shouldn't need a cart.
	*	The relationship is currently backwards.
	*/
	
	/**
	* Get shipping total.
	*/
	function get_shipping_total ($cart, $options = null)
	{
		if ($options['discount'])
		{
			// Override with stored discount value.
			if (is_numeric($cart['shipping_discount']))
			{
				return $cart['shipping_discount'];
			}
			else if ($cart['order']['id'] && is_numeric($cart['order']['shipping_discount']))
			{
				return $cart['order']['shipping_discount'];
			}
		}
		else
		{
			// Override with stored total value.
			if (is_numeric($cart['shipping_total']))
			{
				return $cart['shipping_total'];
			}
			else if ($cart['order']['id'] && is_numeric($cart['order']['shipping_total']))
			{
				return $cart['order']['shipping_total'];
			}
		}
		
		$method_name = $cart['order']['shipping']['method'];
		
		// Get fresh methods/prices.
		$methods = Carts::get_shipping_methods($cart);
		
		// Find the selected method and price.
		foreach ((array)$methods as $method)
		{
			if ($method['name'] == $method_name)
			{
				// Discount total?
				if ($options['discount'])
				{
					return $method['discount'];
				}
				
				$total = $method['price'];
				
				// Adjust total if applicable.
				return $total + $cart['shipping_fee'];
			}
		}
		
		return null;
	}
	
	/**
	* Get tax total.
	*/
	function get_tax_total ($cart)
	{
		// Override with stored total value.
		if (is_numeric($cart['set_tax_total']))
		{
			return $cart['set_tax_total'];
		}
		else if (is_numeric($cart['order']['set_tax_total']))
		{
			return $cart['order']['set_tax_total'];
		}
		
		// Add up applicable taxes.
		foreach ((array)$cart['taxes'] as $key => $tax)
		{
			$tax_total += $tax['price'];
		}
		
		return $tax_total;
	}
	
	/**
	* Get combined discount total.
	*/
	function get_discount_total ($cart)
	{
		return
			$cart['sub_discount']
			+ $cart['shipping_discount'];
	}
	
	/**
	* Get grand total, all totals combined.
	*/
	function get_grand_total ($cart)
	{
		return
			$cart['sub_total']
			+ $cart['shipping_total']
			+ $cart['tax_total']
			- $cart['discount_total'];
	}
	
	/**
	* Get credit total. Validates with account balance.
	*/
	function get_credit_total ($cart)
	{
		if ($cart['order']['credit_total'])
		{
			// Order already set.
			return $cart['order']['credit_total'];
		}
		elseif ($balance = $cart['account']['balance'])
		{
			// Validate/adjust credit total by account balance.
			if ($credit_total = $cart['credit_total'])
			{
				$credit_total = ($credit_total > $balance)
					? $balance
					: $credit_total;
			}
			
			return $credit_total;
		}
		
		return null;
	}
	
	/**
	* Get billing total in cart.
	*/
	function get_billing_total ($cart)
	{
		return $cart['grand_total'] - $cart['credit_total'];
	}
	
	/**
	* Get cart quantity (all items).
	*/
	function get_quantity ($cart)
	{
		foreach ((array)$cart['items'] as $item)
		{
			$quantity += $item['quantity'];
		}
		return round($quantity);
	}
	
	/**
	* Get cart weight (all items including bundles).
	*/
	function get_weight ($cart)
	{
		// Minimum 1.
		$total_weight = 1;
		
		// Add item weight including bundles.
		foreach ((array)$cart['items'] as $item)
		{
			$should_add_to_total = 
				(!$item['is_cancelled'] && !$item['is_returned'])
				|| $cart['order']['status'] == 'cancelled'
				|| $cart['order']['status'] == 'returned';
				
			if ($should_add_to_total)
			{
				$total_weight += $item['weight'] * $item['quantity'];
			}
		}
		
		return $total_weight;
	}
	
	/**
	* Determine whether a cart is abandoned.
	* Returns a soft guess based on last time active.
	*/
	function abandoned ($cart)
	{	
		if ($last_active = strtotime($cart['date_updated'] ?: $cart['date_created']))
		{
			// < 30 minutes?
			if (time() - 1800 < $last_active)
			{
				return false; //"no";
			}
			// 1 hour?
			if (time() - 3600 < $last_active)
			{
				return false; //"maybe";
			}
			// 3 hours?
			if (time() - 10800 < $last_active)
			{
				return true; //"likely";
			}
			else
			{
				return true; //"yes";
			}
		}
		
		return null; //"not sure";
	}
	
	/**
	* Get shipping methods.
	*/
	function get_shipping_methods ($cart)
	{
		// Let cart override?
		if ($cart['shipping_methods'])
		{
			$methods = $cart['shipping_methods'];
		}
		else
		{
			// Zip and weight required.
			if (!$cart['order']['shipping']['zip'] || !$cart['weight'])
			{
				return false;
			}
			
			// Params used to cache shipping methods.
			$params = array(
				'country' => $cart['order']['shipping']['country'],
				'zip' => $cart['order']['shipping']['zip'],
				'weight' => $cart['weight'],
				'discounts' => $cart['discounts']
			);
			
			// Cache for 1 hour.
			$cache_uri = "/cache/shipping_methods/".md5(serialize($params))."?expire=3600";
			$methods = get($cache_uri);
			
			// Not cached?
			if (count($methods) == 0)
			{
				// Get available methods.
				$methods = get("/shipments/methods", $params);
				
				// Put result in cache?
				if ($methods && !$methods['errors'])
				{
					put($cache_uri, $methods);
				}
			}
		}
			
		// Apply discounts.
		$methods = Discounts::apply_shipping($cart, $methods, $cart['discounts']);
		
		return $methods;
	}
	
	/**
	* Get taxes that apply to this cart.
	*/
	function get_taxes ($cart)
	{
		// Let cart override?
		if ($cart['taxes'])
		{
			return $cart['taxes'];
		}
		else
		{
			$taxes = array();
			
			if ($settings = get("/settings/taxes"))
			{
				foreach ($settings as $type => $tax)
				{
					// Not enabled?
					if ($tax['enabled'] === false)
					{
						continue;
					}
					
					// State and country used for tax conditions.
					$cart_state = strtoupper($cart['order']['shipping']['state']);
					$cart_country = strtoupper($cart['order']['shipping']['country']);
					
					// Conditions apply?
					unset($cond_apply);
					if ($cond = $tax['conditions'])
					{	
						$cond_apply = false;
						
						// Shipping state.
						if ($cond['state'])
						{
							// One or many state matches.
							$cond_states = is_array($cond['state']) ? $cond['state'] : array($cond['state']);
							
							foreach ($cond_states as $match)
							{
								// Match [state]_[country]
								if (preg_match('/^'.$match.'$/i', $cart_state.'_'.$cart_country))
								{
									$cond_apply = true;
								}
								// Match [state]
								elseif (preg_match('/^'.$match.'$/i', $cart_state))
								{
									$cond_apply = true;
								}
							}
						}
						
						// Shipping country (code).
						if ($cond['country'])
						{
							// One or many country code matches.
							$cond_countries = is_array($cond['country']) ? $cond['country'] : array($cond['country']);
							
							foreach ($cond_countries as $match)
							{
								// Match [country]
								if (preg_match('/^'.$match.'$/i', $cart_country))
								{
									$cond_apply = true;
								}
							}
						}
						
						// Account role.
						if ($cond['role'])
						{
							// One or many roles.
							$cond_roles = is_array($cond['role']) ? $cond['role'] : array($cond['role']);
							
							foreach ($cond_roles as $role)
							{
								if (in($role, $cart['account']['roles']))
								{
									$cond_apply = true;
								}
							}
						}
					}
					
					// Exemptions apply?
					unset($exempt_apply);
					if ($exempt = $tax['exemptions'])
					{
						$exempt_apply = true;
						
						// Shipping state.
						if ($exempt['state'])
						{
							// One or many state matches.
							$exempt_states = is_array($exempt['state']) ? $exempt['state'] : array($exempt['state']);
							
							foreach ($exempt_states as $match)
							{
								// Match [state]_[country]
								if (preg_match('/^'.$match.'$/i', $cart_state.'_'.$cart_country))
								{
									$exempt_apply = true;
								}
								// Match [state]
								elseif (preg_match('/^'.$match.'$/i', $cart_state))
								{
									$exempt_apply = true;
								}
							}
						}
						
						// Shipping country.
						if ($exempt['country'])
						{
							// One or many country code matches.
							$exempt_countries = is_array($exempt['country']) ? $exempt['country'] : array($exempt['country']);
							
							foreach ($exempt_countries as $match)
							{
								// Match [country]
								if (preg_match('/^'.$match.'$/i', $cart_country))
								{
									$exempt_apply = true;
								}
							}
						}
						
						// Account role.
						if ($exempt['role'])
						{
							// One or many roles.
							$exempt_roles = is_array($exempt['role']) ? $exempt['role'] : array($exempt['role']);
							
							foreach ($exempt_roles as $role)
							{
								if (in($role, $cart['account']['roles']))
								{
									$exempt_apply = true;
								}
							}
						}
					}
					
					// Not applicable?
					if ($cond_apply === false || $exempt_apply === false)
					{
						continue;
					}
					
					switch ($type)
					{
						// Calculate sales tax.
						case 'sales':
						
							// Multiply sub total by tax rate (gross).
							$orig_tax_total = $tax_total = ($cart['sub_total'] - $cart['sub_discount']) * ($tax['rate']/100);
							
							$taxes[$type] = array(
								'name' => $tax['name'],
								'price' => $orig_tax_total,
								'total' => $tax_total
							);
							break;
							
						// Calculate VAT.
						case 'vat':
							
							// @TODO: implement this.
							$taxes[$type] = $tax;
							break;
					}
				}
			}
		}
		
		return $taxes;
	}
}