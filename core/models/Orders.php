<?php
/**
 * Order model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Orders extends AppModel
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
			'account_id',
			'name',
			'email',
			'phone',
			'items',
			'item_ids',
			'shipping',
			'billing',
			'schedule',
			'sub_total',
			'sub_discount',
			'tax_total',
			'shipping_total',
			'shipping_discount',
			'discount_total',
			'grand_total',
			'credit_total',
			'billing_total',
			'product_cost',
			'shipping_cost',
			'coupon_code',
			'discounts',
			'discount_ids',
			'status',
			'error',
			'parent_id',
			'prev_id',
			'next_id',
			'date_created',
			'date_updated',
			'date_scheduled',
			'date_shipped',
			'date_cancelled',
			'date_returned',
			'account' => function ($order)
			{
				return get("/accounts/{$order['account_id']}");
			},
			'status' => function ($order)
			{
				return Orders::get_status($order);
			},
			'payment_total' => function ($order)
			{
				return Orders::get_payment_total($order);
			},
			'payment_balance' => function ($order)
			{
				return Orders::get_payment_balance($order);
			},
			'items' => function ($order)
			{
				return get("/products", array(':with' => $order['items']));
			},
			'items_out_of_stock' => function ($order)
			{
				return Orders::get_items_out_of_stock($order);;
			},
			'discounts' => function ($order)
			{
				return get("/discounts", array(':with' => $order['discounts']));
			},
			'payments' => function ($order)
			{
				return get("/payments", array('order_id' => $order['id'], 'order' => 'date_created ASC', 'limit' => null));
			},
			'shipments' => function ($order)
			{
				return get("/shipments", array('order_id' => $order['id'], 'order' => 'date_created ASC', 'limit' => null));
			},
			'cart' => function ($order)
			{
				return Orders::get_cart_record($order);
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'id',
			'name',
			'email',
			'shipping.name',
			'billing.name',
			'date_created',
			'coupon_code'
		);
		
		// Default query.
		$this->query = array(
			'limit' => 50
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'account_id'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'account_id',
				'name',
				'email',
				'billing',
				'shipping',
				'sub_total',
				'shipping_total',
				'grand_total'
			),
			'email-address' => array(
				'email'
			),
			':items' => array(
				'required' => array(
					'id',
					'price',
					'quantity'
				)
			)
		);
		
		// Event binds.
		$this->binds = array(
			
			// POST order.
			'POST' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Assemble order from cart?
				if ($cart = $data['cart'])
				{
					$order = array_merge((array)$cart['order'], array(
						'id' => null,
						'cart' => null,
						'cart_id' => $cart['id'],
						'account_id' => $cart['account_id'],
						'items' => $cart['items'],
						'coupon_code' => $cart['discounts']['coupon']['code'],
						'discounts' => $cart['discounts'],
						'sub_total' => $cart['sub_total'],
						'sub_discount' => $cart['sub_discount'],
						'shipping_total' => $cart['shipping_total'],
						'shipping_discount' => $cart['shipping_discount'],
						'tax_total' => $cart['tax_total'],
						'discount_total' => $cart['discount_total'],
						'grand_total' => $cart['grand_total'],
						'credit_total' => $cart['credit_total'],
						'billing_total' => $cart['billing_total'],
						'product_cost' => $cart['product_cost']
					));
					
					// Remember discount IDs.
					foreach ((array)$order['discounts'] as $discount)
					{
						$order['discount_ids'][] = $discount['id'];
					}
					
					// Default auto payment.
					$order['auto_payment'] = $order['auto_payment'] ?: true;
					
					// Send e-mail by default.
					if (!isset($data[':email']))
					{
						$data[':email'] = true;
					}
				}
				// Copy from another order?
				elseif ($data['order'])
				{
					$order = $data['order'];
					
					// Change certain properties.
					$order['parent_id'] = $order['parent_id'] ?: $order['id'];
					$order['prev_id'] = $order['id'];
					$order['status'] = 'pending';
					
					// Unset certain properties.
					unset($order['date_created']);
					unset($order['date_updated']);
					unset($order['date_scheduled']);
					unset($order['next_id']);
					unset($order['_id']);
					unset($order['id']);
				}
				elseif ($data['account_id'])
				{
					$order = $data;
					
					// New order by account id?
					if ($account = get("/accounts/{$order['account_id']}"))
					{
						// Set defaults from account.
						$order['name'] = $order['name'] ?: $account['name'];
						$order['email'] = $order['email'] ?: $account['email'];
						$order['phone'] = $order['phone'] ?: $account['phone'];
						
						// Default to account shipping/billing, filter account arrays for validation.
						$order['shipping'] = $order['shipping'] ?: array_filter((array)$account['shipping']);
						$order['billing'] = $order['billing'] ?: array_filter((array)$account['billing']);
						
						unset($order['shipping']['default']);
						unset($order['billing']['default']);
					}
					else
					{
						// Bad account.
						return false;
					}
				}
				elseif ($data['account'])
				{
					$order = $data;
					
					// New account.
					$account = post("/accounts", $data['account']);
					
					if ($account['errors'])
					{
						$model->errors['account'] = $account['errors'];
					}
					else
					{
						unset($order['account']);
						$order['account_id'] = $account['id'];
						$order['name'] = $account['name'];
						$order['email'] = $account['email'];
						$order['phone'] = $account['phone'];
					}
				}
				
				// Default status.
				if (!isset($order['status']))
				{
					$order['status'] = Orders::get_status($order);
				}
				
				// Default schedule date.
				$order['date_scheduled'] = Orders::get_date_scheduled($order);
				
				// Filter order items.
				// @TODO: Fix this to accept more but ignore :with stuff
				$order['items'] = Orders::get_items_for_update($order['items']);
				
				// Post it.
				$event['data'] = $order;
			},
			
			// PUT order.
			'PUT' => function ($event, $model)
			{
				$data =& $event['data'];
				
				if ($order = $model->get($event['id']))
				{	
					// Update billing info? Merge.
					if ($data['billing'])
					{
						$data['billing'] = array_merge(
							(array)$order['billing'],
							(array)$data['billing']
						);
					}
					
					// Update shipping info? Merge.
					if ($data['shipping'])
					{
						$data['shipping'] = array_merge(
							(array)$order['shipping'],
							(array)$data['shipping']
						);
					}
					
					// Update account info?
					if ($order['account'])
					{
						if ($order['name'] != $order['account']['name'])
						{
							$data['name'] = $order['account']['name'];
						}
						
						if ($order['email'] != $order['account']['email'])
						{
							$data['email'] = $order['account']['email'];
						}
						
						if ($order['phone'] != $order['account']['phone'])
						{
							$data['phone'] = $order['account']['phone'];
						}
					}
					
					// Update schedule?
					if (isset($data['schedule']))
					{
						$data['prev_id'] = $order['prev_id'];
						$data['date_scheduled'] = Orders::get_date_scheduled($data);
					}
					
					// Removed coupon code?
					if (isset($data['coupon_code']) && !$data['coupon_code'])
					{
						// Remove coupon.
						$data['discounts'] = $order['discounts'];
						unset($data['discounts']['coupon']);
					}
					// Applied new coupon code?
					elseif ($data['coupon_code'] && ($data['coupon_code'] != $order['coupon_code'] || !$order['discounts']['coupon']))
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
						
						// Update items with discount rules.
						// @TODO: shouldn't have to convert to array like this.
						$data['items'] =
							($order['items'] instanceof Traversable)
							? iterator_to_array($order['items'])
							: $order['items'];
							
						foreach ((array)$discount['rules'] as $rule)
						{
							if ($rule['add'] && $rule['product_id'])
							{
								// Exists in cart?
								$exists = false;
								foreach ((array)$data['items'] as $item_id => $item)
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
									$data['items'][$exists]['quantity'] = $rule['quantity'];
								}
								else
								{
									// New item.
									$data['items'][] = array(
										'id' => $rule['product_id'],
										'quantity' => $rule['quantity']
									);
									
									// @TODO: Find a beter way to make sure ID is never 0.
									if ($data['items'][0])
									{
										$data['items'][1] = $data['items'][0];
										unset($data['items'][0]);
									}
								}
							}	
						}
						
						$data['discounts'] = $order['discounts'];
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
						// Remember discount IDs for easy lookup.
						$data['discount_ids'] = array();
						foreach ((array)$data['discounts'] as $did => $discount)
						{
							$data['discount_ids'][] = $discount['id'];
							
							// Unset some discount details.
							unset($data['discounts'][$did]['codes']);
							unset($data['discounts'][$did]['codes_used']);
							unset($data['discounts'][$did]['code_history']);
							unset($data['discounts'][$did]['conditions']);
						}
					}
					
					// Cancel order?
					if ($data[':cancel'])
					{
						foreach ((array)$order['items'] as $id => $item)
						{
							if (!$item['is_returned'])
							{
								$item['is_cancelled'] = true;
								$data['items'][$id] = $item;
							}
						}
						
						$data['status'] = 'cancelled';
						$data['date_cancelled'] = time();
					}
					
					// Filter order items?
					if ($data['items'])
					{
						$data['items'] = Orders::get_items_for_update($data['items']);
					}
					
					// Recalculate order totals?
					if ($data[':recalc'])
					{
						$order['shipping'] = $data['shipping'] ?: $order['shipping'];
						
						$order['billing'] = $data['billing'] ?: $order['billing'];
						
						// Allow shipping total to be set manually.
						if (isset($data['shipping_total']))
						{
							if (is_numeric($data['shipping_total']))
							{
								$order['shipping_total'] = $data['shipping_total'];
							}
							else
							{
								$order['shipping_total'] = null;
							}
						}
						
						// Allow tax total to be set manually.
						if (isset($data['tax_total']))
						{
							if (is_numeric($data['tax_total']))
							{
								$order['set_tax_total'] = $data['set_tax_total'] = $data['tax_total'];
							}
							else
							{
								$order['set_tax_total'] = $data['set_tax_total'] = null;
							}
						}
						
						// Use cart model to calc totals.
						$data = array_merge($data, array(
							'sub_total' => round($order['cart']['sub_total'], 2),
							'sub_discount' => round($order['cart']['sub_discount'], 2),
							'shipping_total' => round($order['cart']['shipping_total'], 2),
							'shipping_discount' => round($order['cart']['shipping_discount'], 2),
							'tax_total' => round($order['cart']['tax_total'], 2),
							'discount_total' => round($order['cart']['discount_total'], 2),
							'grand_total' => round($order['cart']['grand_total'], 2),
							'billing_total' => round($order['cart']['grand_total'], 2),
							'product_cost' => round($order['cart']['product_cost'], 2)
						));
					}
				}
			},
			
			// After POST, PUT order.
			'after:POST, after:PUT' => function ($result, $event, $model)
			{
				$data =& $event['data'];
				$orig =& $event['orig'];
			
				// Orig vs new coupon.
				$orig_coupon = array('id' => $orig['discounts']['coupon']['id'], 'code' => $orig['coupon_code']);
				$new_coupon = array('id' => $data['discounts']['coupon']['id'], 'code' => $data['coupon_code']);
				
				if ($event['id'] && isset($data['discounts']) && isset($data['coupon_code']) && $orig_coupon != $new_coupon)
				{
					// Undo orig coupon used?
					if ($orig['discounts']['coupon'])
					{
						put("/discounts/{$orig_coupon['id']}", array(
							'code_used' => $orig_coupon['code'],
							':undo' => true
						));
					}
					
					// Used new coupon?
					if ($data['discounts']['coupon'])
					{
						put("/discounts/{$new_coupon['id']}", array(
							'code_used' => $new_coupon['code']
						));
					}
				}
				
				// Update order totals?
				if ($event['id'])
				{
				 	if (isset($data['discounts']) || isset($data['items']))
					{
						// Update order totals.
						$model->put($event['id'], array(':recalc' => true));
					}
				}
				
				// Relate to cart?
				if ($cart_id = $data['cart_id'])
				{
					put("/carts/{$cart_id}/order_id", $result['id']);
				}
				
				// Send order receipt e-mail?
				if ($data[':email'])
				{
					$settings = get("/settings/emails/order");
					
					if ($settings !== false)
					{
						$settings['order'] = $result;
						$settings['to'] = $result['email'];
						
						// Default subject?
						if ($settings['subject'])
						{
							$settings['subject'] .= ' #'.$result['id'];
						}
						
						// Override default email settings?
						if (is_array($data[':email']))
						{
							$settings = array_merge($settings, $data[':email']);
						}
						
						post("/emails/order", $settings);
					}
				}
			},
			
			// POST, PUT order items.
			'POST.items, PUT.items' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Filter item.
				$items = array($data);
				$data = array_pop(Orders::get_items_for_update($items));
				
				if ($order = $model->get($event['id']))
				{
					$product = get("/products/{$data['id']}", array(
						'pricing' => array(
							'roles' => $order['account']['roles'],
							'quantity' => $data['quantity'] ?: 1
						)
					));
					
					// Default price?
					if ($product && isset($data['price']) && $data['price'] === "")
					{	
						$data['price'] = $product['price'];
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
				}
			},

			// After POST, PUT, DELETE order items.
			'after:POST.items, after:PUT.items, after:DELETE.items' => function ($result, $event, $model)
			{
				$data =& $event['data'];
				
				$order_id = $event['id'];
				$order = $model->get($order_id);

				// All items cancelled/returned?					
				$all_items_cancelled = true;
				$all_items_returned = true;
				foreach ((array)$order['items'] as $i)
				{
					if (!$i['is_cancelled']) $all_items_cancelled = false;
					if (!$i['is_returned']) $all_items_returned = false;
				}
				if ($order['items'] && $all_items_cancelled && !$order['date_cancelled'])
				{
					$model->put($order_id, array('status' => 'cancelled', 'date_cancelled' => time()));
				}
				else if (!$all_items_cancelled && $order['date_cancelled'])
				{
					$model->put($order_id, array('date_cancelled' => null));
				}
				if ($order['items'] && $all_items_returned && !$order['date_returned'])
				{
					$model->put($order_id, array('status' => 'cancelled', 'date_returned' => time()));
				}
				else if (!$all_items_returned && $order['date_returned'])
				{
					$model->put($order_id, array('date_returned' => null));
				}
				
				if ($data['is_cancelled'])
				{
					// @TODO: Return item qty to stock.
				}
				
				if ($data['is_cancelled'] || $data['is_returned'])
				{
					// @TODO: Remove item price/cost from product, sub, cost, grand totals.
				}
				
				// Update order totals.
				$model->put($order_id, array(':recalc' => true));
			},
			
			// After POST items.
			'after:POST.items' => function ($result, $event, $model)
			{
				// Update order totals.
				//$model->put($event['id'], array(':recalc' => true));
				
				// @TODO: Remove/replace item qty from stock.
			},
			
			// After DELETE items.
			'after:DELETE.items' => function ($result, $event, $model)
			{
				// Update order totals.
				//$model->put($event['id'], array(':recalc' => true));
				
				// @TODO: Remove/replace item qty from stock.
			}
		);
	}
	
	/**
	* Get contextual order status by event dates.
	*/
	function get_status ($order)
	{
		$status = 'pending';
		
		// Some states change by property (i.e. "date_shipped") and some static (i.e. "incomplete").
		if ($order['status'] && !in_array($order['status'], array('pending', 'ready', 'shipped', 'cancelled', 'returned')))
		{
			$status = $order['status'];
		}
		elseif ($order['payment_balance'] >= 0)
		{
			$status = 'ready';
			
			if ($order['date_shipped'])
			{
				$status = 'shipped';
			}
			if ($order['date_returned'])
			{
				$status = 'returned';
			}
		}
		if ($order['date_cancelled'])
		{
			$status = 'cancelled';
		}
		
		// Update status?
		if ($order['id'] && $order['status'] != $status)
		{
			put("/orders/{$order['id']}/status", $status);
		}
		
		return $status;
	}
	
	/**
	* Get payment total.
	*/
	function get_payment_total ($order)
	{
		$total = 0;
		foreach ((array)$order['payments'] as $payment)
		{
			if ($payment['action'] == 'charge' || $payment['action'] == 'refund')
			{
				$total += $payment['amount'];
			}
		}
		
		return $total;
	}
	
	/**
	* Get payment balance (from grand total).
	*/
	function get_payment_balance ($order)
	{
		if ($order['date_cancelled'] || $order['date_returned'])
		{
			$grand_total = 0;
		}
		else
		{
			$grand_total = $order['grand_total'];
		}
		
		return round($order['payment_total'], 2) - round($grand_total, 2);
	}
	
	/**
	* Get cart model record (anonymous).
	*/
	function get_cart_record ($order)
	{
		$cart = array(
			'items' => $order['items'],
			'discounts' => $order['discounts'],
			'account_id' => $order['account_id'],
			'session_id' => session_id(),
			'order' => ($order instanceof ArrayIterator) ? iterator_to_array($order) : $order
		);
		return new ModelRecord($cart, 'Carts');
	}
	
	/**
	* Get order items for update.
	*/
	function get_items_for_update ($items)
	{
		foreach ((array)$items as $key => $item)
		{
			$items[$key] = array_filter(array(
				'id' => $item['id'],
				'quantity' => $item['quantity'],
				'price' => $item['price'],
				'options' => $item['options'],
				'variant' => $item['variant'],
				'variant_id' => $item['variant_id'],
				'is_cancelled' => $item['is_cancelled'],
				'is_returned' => $item['is_returned'],
				':delete' => $item[':delete'],
				':validate' => $item[':validate']
			));
			
			if (isset($item['price']))
			{
				$items[$key]['price'] = $item['price'];
			}
		}
		
		return $items;
	}
	
	/**
	* Get order items out of stock.
	*/
	function get_items_out_of_stock ($order)
	{
		foreach ((array)$order['items'] as $item)
		{
			if ($item['out_of_stock'])
			{
				$out_of_stock_items[$item['id']] = $item;
			}
			if ($item['items'])
			{
				foreach ((array)$item['items'] as $i)
				{
					if ($i['out_of_stock'])
					{
						$out_of_stock_items[$i['id']] = $i;
					}
				}
			}
		}
		
		return $out_of_stock_items;
	}
	
	/**
	* Get the next scheduled order date.
	*/
	function get_date_scheduled ($order)
	{
		// Already scheduled?
		if ($order['date_scheduled'])
		{
			return $order['date_scheduled'];
		}
		
		$sch = $order['schedule'];
		
		// Must be defined by date or recurring.
		if (!$sch['date'] && !$sch['every'])
		{
			return null;
		}
		
		$prev_order = get("/orders/{$order['prev_id']}");
			
		// Date must pass this deadline.
		// If schedule changed from previous order in series, look forward only.
		$last_time = ($prev_order && $prev_order['schedule'] == $sch)
			? strtotime($prev_order['date_scheduled'] ?: $prev_order['date_created'])
			: time()-86400;
		
		// Schedule after last order?
		$schedule_time = $prev_order
			? strtotime($prev_order['date_scheduled'] ?: $prev_order['date_created'])
			: time();
		
		// Yearly recurring order.
		if ($sch['every'] == "year")
		{
			$day = $sch['day'] ?: date('z', $schedule_time);
			
			if ($day > 0 && $day <= 365)
			{
				// Add number of days from first of the year.
				$add = (($day-1) * (86400 * 365));
				
				// Start with this year (null time).
				$schedule_time = strtotime("first day of this year today", $schedule_time) + $add;
				
				// Already passed last date? Next year.
				if ($schedule_time <= $last_time)
				{
					$schedule_time = strtotime("first day of next year today", $schedule_time) + $add;
				}
			}
		}
		// Monthly recurring order.
		else if ($sch['every'] == "month")
		{
			$day = $sch['day'] ?: date('j', $schedule_time);
			
			if ($day > 0 && $day <= 31)
			{
				// Add number of days from first of the month.
				$add = (($day-1) * 86400);
				
				// Start with this month (null time).
				$schedule_time = strtotime("first day of this month today", $schedule_time) + $add;
				
				// Already passed last date? Next month.
				if ($schedule_time <= $last_time)
				{
					$schedule_time = strtotime("first day of next month today", $schedule_time) + $add;
				}
				
				// Add span multiplier?
				for ($span = $sch['span']; $prev_order && $span > 1; $span--)
				{
					$schedule_time = strtotime("first day of next month today", $schedule_time) + $add;
				}
			}
		}
		// Weekly recurring order.
		else if ($sch['every'] == "week")
		{
			$day = $sch['day'] ?: date('w', $schedule_time);
			
			if ((string)$day === "0")
			{
				// Shift sunday to day 7.
				$day = 7;
			}
			
			if ($day > 0 && $day <= 7)
			{
				$weekdays = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
				$weekday = $weekdays[ $day-1 ];
				
				$schedule_time = strtotime("next {$weekday}", $schedule_time);
				
				// Already passed last date? Next week.
				if ($schedule_time <= $last_time)
				{
					$schedule_time = $schedule_time + (86400 * 7);
				}
				
				// Add span multiplier?
				for ($span = $sch['span']; $prev_order && $span > 1; $span--)
				{
					$schedule_time = $schedule_time + (86400 * 7);
				}
			}
		}
		// One-time schedule date.
		else if ($sch['date'])
		{
			$schedule_time = strtotime($sch['date']);
		}

		return $schedule_time;
	}
}
