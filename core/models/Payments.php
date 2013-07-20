<?php
/**
 * Payment model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Payments extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{	
		// Fields.
		$this->fields = array(
			'id',
			'ref',
			'account_id',
			'order_id',
			'method',
			'action',
			'amount',
			'status',
			'reason',
			'date_created',
			'date_updated',
			'status' => function ($payment)
			{
				return Payments::get_status($payment);
			},
			'account' => function ($payment)
			{
				return get("/accounts/{$payment['account_id']}");
			},
			'order' => function ($payment)
			{
				return get("/orders/{$payment['order_id']}");
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'id',
			'ref',
			'reason'
		);
		
		// Optional reference key.
		$this->slug_pk = 'ref';
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique', 'ref'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'account_id',
				'order_id',
				'method',
				'action',
				'billing',
				'amount',
				'reason',
				'status'
			),
			'amount',
			'unique' => array(
				//'ref' // Allow it to be overwritten for now @TODO: fix unique on slugs
			)
		);
		
		// Event binds.
		$this->binds = array(
		
			// POST payment.
			'POST' => function ($event, $model)
			{
				$data =& $event['data'];
				
				if ($order = get("/orders/{$data['order_id']}"))
				{
					// Default data from order.
					$data['account_id'] = $order['account_id'];
					$data['method'] = $data['method'] ?: $order['billing']['method'];
					$data['action'] = $data['action'] ?: "charge";
				}
				else
				{
					$model->error('Order #'.$data['order_id'].' not found', 'order_id');
				}
				
				// Payment data invalid?
				if (!$model->validate($data))
				{
					// Prevent further processing.
					return false;
				}
				
				// Method/action case insensitive.
				$data['method'] = strtolower($data['method']);
				$data['action'] = strtolower($data['action']);
				
				// Get default status.
				$data['status'] = Payments::get_status($data);
				
				// Process default methods (i.e. cash, account credit, invoice).
				try {
					$data = Payments::process_default_methods($data);
				}
				catch (Exception $e)
				{
					$model->error($e->getMessage(), 'method');
					return false;
				}
			},
			
			// Make sure amount is valid.
			'validate:amount' => function ($value, $field, $params, $model)
			{
				if ($value == 0)
				{
					$model->error('invalid', 'amount');
				}
			}
		);
	}
	
	/**
	 * Process and validate data for default methods.
	 */
	function process_default_methods ($payment)
	{
		$settings = get("/settings/payments");
		
		// Adjust amount for refunds/credits.
		if (in_array($payment['action'], array('credit', 'refund')))
		{
			$payment['amount'] = -(abs($payment['amount']));
		}
		
		switch ($payment['method'])
		{
			// Cash payment.
			case 'cash':
			
				$payment['status'] = 'success';
				break;
			
			// Credit payment.
			case 'credit':
			
				if ($payment['action'] == 'charge')
				{
					// Check account balance before charge.
					$account = get("/accounts/{$payment['account_id']}");
					
					if ($account['balance'] < $payment['amount'])
					{
						throw new Exception("Account lacks funds ($".total($account['balance']).")");
					}
				}
				$payment['status'] = 'success';
				break;
			
			// Invoice payment.
			case 'invoice':
			
				// Default due date based on account terms.
				if ($account = get("/accounts/{$payment['account_id']}"))
				{
					// Net days by account terms or default setting.
					$net_days = $account['terms']['net_days'] ?: $settings['invoice']['net_days'];
					
					if ($net_days)
					{
						$payment['date_due'] = date('Y-m-d', time() + ($net_days*86400));
					}
				}
				break;
		}
		
		return $payment;
	}
	
	/**
	* Get contextual payment status.
	*/
	function get_status ($payment)
	{
		// Default pending status.
		$status = 'pending';
		
		// Adjust status based on certain event dates.
		if (in_array($payment['status'], array('pending', 'sent', 'paid', 'cancelled', 'overdue')))
		{
			if ($payment['date_sent'])
			{
				$status = 'sent';
			}
			if ($payment['date_paid'])
			{
				$status = 'paid';
			}
			elseif ($payment['date_due'] && strtotime($payment['date_due']) < time())
			{
				$status = 'overdue';
			}
		}
		if ($payment['date_cancelled'])
		{
			$status = 'cancelled';
		}
		
		// Update status?
		if ($payment['id'] && $payment['status'] != $status)
		{
			// Put or remove overdue flag on order.
			if ($status == "overdue")
			{
				put("/orders/{$payment['order_id']}", array('overdue' => true));
			}
			elseif ($payment['status'] == "overdue")
			{
				put("/orders/{$payment['order_id']}", array('overdue' => false));
			}
			
			put("/payments/{$payment['id']}/status", $status);
		}
		
		return $status;
	}
}