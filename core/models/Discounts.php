<?php
/**
 * Discount model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Discounts extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{
		// Fields.
		$this->fields = array(
			'id',
			'name',
			'rules',
			'apply_to',
			'conditions',
			'description',
			'codes',
			'codes_used',
			'account_roles',
			'account_ids',
			'is_valid',
			'code_history',
			'date_created',
			'date_updated',
			'is_valid' => function ($discount)
			{
				return Discounts::is_valid($discount);
			},
			'orders' => function ($discount)
			{
				return get("/orders", array('discount_ids' => $discount['id']));
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'name',
			'description',
			'codes'
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'name',
				'description',
			),
			'date' => array(
				'conditions.date_valid',
				'conditions.date_expires'
			),
			':rules' => array(
				'required' => array(
					'value',
					'product_id',
					'category_id',
					'account_role',
					'account_id'
				)
			)
		);
		
		// Event binds.
		$this->binds = array(
		
			// GET discount.
			'GET' => function ($event)
			{
				$params =& $event['data'];
				
				// Get by code? Returns first match.
				if ($code = $params['code'] ?: $params[':code'])
				{
					unset($params['code']);
					
					// Code needs to be uppercase.
					$params[':code'] = strtoupper($code);
					
					// Search codes.
					$params['codes'] = $params[':code'];
				
					// Return first valid code?
					if ($params['is_valid'] && !$params[':first'])
					{
						if ($event['id'])
						{
							$params['id'] = $event['id'];
						}
						
						$params[':first'] = true;
						return get("/discounts", $params);
					}
				}
				// Get valid discounts by other params.
				else if ($params['is_valid'])
				{
					$params['$or'][] = array('apply_to' => 'all');
					
					if (isset($params['account_role']))
					{
						$params['$or'][] = array('account_roles' => $params['account_role']);
						unset($params['account_role']);
					}
					elseif (isset($params['account_roles']))
					{
						$params['$or'][] = array('account_roles' => $params['account_roles']);
						unset($params['account_roles']);
					}
					if (isset($params['account_id']))
					{
						$params['$or'][] = array('account_ids' => $params['account_id']);
						unset($params['account_id']);
					}
					elseif (isset($params['account_ids']))
					{
						$params['$or'][] = array('account_ids' => $params['account_ids']);
						unset($params['account_ids']);
					}
				}
			},
			
			// After GET discount.
			'after:GET' => function ($result, $event)
			{
				$params =& $event['data'];
				
				// Remember the code used to lookup this discount?
				if ($result && $params[':code'])
				{
					$result['code'] = $params[':code'];
				
					// Still valid (single code)?
					if ($params['is_valid'] && !$result['is_valid'])
					{
						return false;
					}
				}
				
				return $result;
			},
			
			// POST, PUT discount.
			'POST, PUT' => function ($event)
			{
				$data =& $event['data'];
				
				// Codes?
				if (isset($data['codes']))
				{
					if (is_array($data['codes']))
					{
						$data['codes'] = implode("\n", $data['codes']);
					}
					if (is_string($data['codes']))
					{
						// Split string into array, unique, uppercase.
						$data['codes'] = array_unique(preg_split('/[^a-z0-9]+/i', strtoupper(trim($data['codes']))));
					}
				}
				
				// Default active.
				if (!isset($data['conditions']['is_active']))
				{
					$data['conditions']['is_active'] = true;
				}
				
				// Don't save code.
				unset($data['code']);
				
				// Update discount?
				if ($discount = get("/discounts/{$event['id']}"))
				{
					// Used a code? Remember it.
					// Might need a more scalable way to do this in the future.
					// @TODO: Consider a separate model for codes. Could be thousands per!?
					if ($data['code_used'] && is_string($data['code_used']))
					{
						$used = $discount['codes_used'];
						
						// Add or remove used code?
						if ($data[':undo'])
						{
							$key = array_search($data['code_used'], (array)$used);
							unset($used[$key]);
						}
						else if ($discount['is_valid'])
						{
							$used[] = $data['code_used'];
						}
						
						// Updated codes used.
						$data['codes_used'] = $used;
						
						// Don't save the single used code.
						unset($data['code_used']);
					}
					
					// Merge conditions always.
					if ($data['conditions'])
					{
						$data['conditions'] = array_merge((array)$discount['conditions'], (array)$data['conditions']);
					}
				}
			},
			
			// PUT discount.
			'PUT' => function ($event)
			{
				$data =& $event['data'];
				
				if ($discount = get("/discounts/{$event['id']}"))
				{
					if ($data['codes'])
					{
						$history = $discount['code_history'] ?: array();
						$codes = $data['codes'] ?: array();
						
						// Save code history, forever.
						$data['code_history'] = array_unique(
							array_merge(
								(array)$history, (array)$codes
							)
						);
					}
				}
			},
						
			// POST, PUT discount rules.
			'POST.rules, PUT.rules' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Default $ value?
				if ($data['value'] && strpos($data['value'], '%') === false && strpos($data['value'], '$') === false)
				{
					$data['value'] = '$'.$data['value'];
				}
			},
						
			// Validate a date string.
			'validate:date' => function ($value, $field, $params, $model)
			{
				if (!empty($value) && is_string($value) && strtotime($value) == false)
				{
					$model->error($params['message'] ?: 'invalid', $field, $params['value_key']);
				}
			}
		);
	}
	
	/**
	* Determine if a discount is currently valid.
	*/
	function is_valid ($discount)
	{
		$is_valid = true;
		
		$cond = $discount['conditions'];
		
		// Date valid from?
		if ($cond['date_valid'] && strtotime($cond['date_valid']) > time())
		{
			$is_valid = false;
		}
		// Date expires on?
		if ($cond['date_expires'] && strtotime($cond['date_expires']) < time())
		{
			$is_valid = false;
		}
		// Arbitrarily active?
		if (!$cond['is_active'])
		{
			$is_valid = false;
		}
		// Reached max total uses?
		if ($cond['max'] > 0 && count($cond['used']) >= $cond['max'])
		{
			$is_valid = false;
		}
		
		// Reached max uses per code?
		if ($cond['max_per_code'] > 0 && count($discount['codes_used']) > 0)
		{
			$count = array_count_values($discount['codes_used']);
			
			// All codes reached max?
			$all_maxed = true;
			foreach ((array)$discount['codes'] as $code)
			{
				if ($count[$code] < $cond['max_per_code'])
				{
					$all_maxed = false;
					break;
				}
			}
			if ($all_maxed)
			{
				$is_valid = false;
			}
		}
		
		// Update validity?
		if ($discount['id'] && $discount['is_valid'] != $is_valid)
		{
			put("/discounts/{$discount['id']}", array('is_valid' => $is_valid));
		}
		
		// Single code valid?
		if (isset($discount['code']) && !empty($discount['codes']))
		{
			// Code reached max?
			if ($count && $cond['max_per_code'] > 0)
			{
				if ($count[$discount['code']] >= $cond['max_per_code'])
				{
					return false;
				}
			}
			
			// Empty code?
			if (empty($discount['code']))
			{
				return false;
			}
		}
		
		return $is_valid;
	}
	
	/**
	* Apply discount value to total.
	*/
	function apply_value ($total, $value)
	{
		if ($value)
		{
			$total = (float)$total;
			$abs_value = (float)preg_replace('/[^0-9]/', '', $value) ?: 0;
			
			// Markup syntax? (+)
			if ($value[0] == '+')
			{
				$abs_value = -($abs_value);
			}
			
			// Percent value?
			if (substr($value, -1, 1) == '%')
			{	
				$total = $total - ($total*($abs_value/100));
			}
			// Abs value.
			else
			{
				$total -= $abs_value;
			}
		}
		
		return $total;
	}
		
	/**
	* Apply shipping discount.
	*/
	function apply_shipping ($cart, $methods, $discounts)
	{
		foreach ((array)$discounts as $id => $discount)
		{
			foreach ((array)$discount['rules'] as $rule)
			{
				if ($rule['enabled'] !== null && !$rule['enabled'])
				{
					continue;
				}
				if ($rule['type'] == "shipping")
				{
					// Minimum sub total?
					if (is_numeric($rule['min_total']))
					{
						if ($cart['sub_total'] < $rule['min_total'])
						{
							continue;
						}
					}
					foreach ((array)$methods as $key => $method)
					{
						$orig_price = $method['price'];
						
						// Already set price?
						if ($cart['order']['shipping']['method'] == $method['name'] && is_numeric($cart['order']['shipping_total']))
						{
							$orig_price = $cart['order']['shipping_total'];
						}
						
						// Specific method or all methods?
						if (empty($rule['method']) || strcasecmp($rule['method'], $method['name']) == 0)
						{
							$methods[$key]['total'] = self::apply_value($orig_price, $rule['value']);
							
							$methods[$key]['discount'] = $orig_price - $methods[$key]['total'];
						}
					}
				}
			}
		}
		
		return $methods;
	}
	
	/**
	* Apply tax discount.
	*/
	function apply_tax ($cart, $tax_total, $discounts)
	{
		foreach ((array)$discounts as $discount)
		{
			foreach ((array)$discount['rules'] as $rule)
			{
				if ($rule['enabled'] !== null && !$rule['enabled'])
				{
					continue;
				}
				if ($rule['type'] == "tax")
				{
					$tax_total = self::apply_value($tax_total, $rule['value']);
				}
			}
		}
		
		return $tax_total;
	}
	
	/**
	* Apply sub total discount.
	*/
	function apply_sub_total ($cart, $sub_total, $discounts)
	{
		foreach ((array)$discounts as $discount)
		{	
			foreach ((array)$discount['rules'] as $rule)
			{
				if ($rule['enabled'] !== null && !$rule['enabled'])
				{
					continue;
				}
				switch ($rule['type'])
				{
					case 'product':
						foreach ((array)$cart['items'] as $item)
						{
							if ($item['id'] != $rule['product_id'])
							{
								continue;
							}
							
							$d_qty = $discount_qty[$item['id']];

							// Limit discount quantity?
							if ($rule['quantity'])
							{
								$qty = ($rule['quantity'] > $item['quantity']) ? $item['quantity'] : $rule['quantity'];
								
								if ($d_qty + $qty >= $rule['quantity'])
								{
									$qty = $qty - ($discout_qty + $qty - $rule['quantity']);
								}
							}
							else
							{
								$qty = $item['quantity'];
							}
							
							// Subtract item from total.
							$sub_total -= ($item['price']*$qty);
							
							// Apply discount value to price.
							$item_price = self::apply_value($item['price'], $rule['value']);
							
							// Add new item price back to total.
							$sub_total += ($item_price*$qty);
							
							// Track total qty discounted.
							$discount_qty[$item['id']] += $qty;
						}
						break;
						
					case 'category':
						foreach ((array)$cart['items'] as $item)
						{
							if (in_array($rule['category_id'], (array)$item['category_ids']))
							{
								$d_qty = $discount_qty[$item['id']];
								
								// Limit discount quantity?
								if ($rule['quantity'])
								{
									$qty = ($rule['quantity'] > $item['quantity'] ? $item['quantity'] : $rule['quantity']);
									
									if ($d_qty + $qty  > $rule['quantity'])
									{
										$qty = $qty - ($discout_qty + $qty - $rule['quantity']);
									}
								}
								else
								{
									$qty = $item['quantity'];
								}
								
								// Subtract item from total.
								$sub_total -= ($item['price']*$qty);
								
								// Apply discount value to price.
								$item_price = self::apply_value($item['price'], $rule['value']);
								
								// Add new item price back to total.
								$sub_total += ($item_price*$qty);
								
								// Track total qty discounted.
								$discount_qty[$item['id']] += $qty;
								
								$items_discounted++;
							}
							
							// Break if reached category limit.
							if ($rule['limit'] && $items_discounted >= $rule['limit'])
							{
								break;
							}
						}
						break;
						
					case '':
					case 'total':
						$sub_total = Discounts::apply_value($sub_total, $rule['value']);
						break;
				}
			}
		}
		
		return $sub_total;
	}
}