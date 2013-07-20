<?php
/**
 * Stripe payment gateway plugin.
 *
 *	Config example (yml):
 *		settings:
 *		  payments:
 *		    card:
 *		      enabled: true
 *		      gateway: stripe
 *		      secret_key: <stripe-secret-key>
 *		      publishable_key: <stripe-publishable-key>
 */

bind('payments', 'post', function ($event, $model)
{
	$data =& $event['data'];
	
	// Is this payment method card, status pending?
	if ($data['method'] != 'card' || $data['status'] != 'pending')
	{
		return;
	}
	
	// Get card payment settings.
	$settings = get("/settings/payments/card");
	
	// Is card payment enabled? Is the gateway stripe?
	if ($settings['enabled'] && $settings['gateway'] == 'stripe')
	{
		try {
			// Process stripe payment data.
			$data = fwd_stripe_process($data, $settings);
		}
		catch (Exception $e)
		{
			$model->error($e->getMessage(), 'method');
			return false;
		}
	}
	
	// Success or pass.
	return;
});

/**
 * Prepare stripe data for processing.
 */
function fwd_stripe_prepare ($data, $settings)
{
	// Load stripe library?
	if (!class_exists('Stripe'))
	{
		require_once(dirname(__FILE__).'/stripe/lib/Stripe.php');
	}
	
	// Set API key.
	Stripe::setApiKey($settings['secret_key']);

	$order = get("/orders/{$data['order_id']}");
	$stripe = $order['billing']['stripe'];
	
	// Need to convert token to customer?
	if ($stripe['object'] == "token")
	{
		if ($stripe['used'] == 'true')
		{
			throw new Exception('Stripe token already used');
		}			

		$customer = Stripe_Customer::create(array(
			'description' => $order['billing']['name'],
			'card' => $stripe['id']
		));
		
		$billing['stripe'] = $customer->__toArray(true);
		$billing['card'] = $billing['stripe']['active_card'];
		unset($billing['stripe']['active_card']);
		
		// Update order.
		put($order, array('billing' => $billing));
		
		// Update account billing also?
		if (($account_billing = $order['account']['billing'])
			&& $account_billing['method'] == 'card'
			&& $account_billing['stripe']['id'] == $stripe['id'])
		{
			$account_billing['stripe'] = $billing['stripe'];
			$account_billing['card'] = $billing['card'];
			
			put($order['account'], array('billing' => $account_billing));
		}
	}
	
	return $data;
}

/**
 * Process stripe payment.
 */
function fwd_stripe_process ($data, $settings)
{
	// Prepare stripe payment first.
	$data = fwd_stripe_prepare($data, $settings);

	// Prepare returned error?
	if (isset($data['_error']))
	{
		return $data;
	}
	
	// Default action is charge.
	$data['action'] = $data['action'] ?: 'charge';
	
	// Get customer token from order id.
	$order = get("/orders/{$data['order_id']}");
	$customer = $order['billing']['stripe'];
	$data['card'] = $order['billing']['card'];
	
	if ($customer['object'] != 'customer')
	{
		throw new Exception('Stripe customer ID missing');
	}
	
	// Amount default? If it's the first payment and order has balance.
	if ($data['amount'] == null && !$order['payments'] && $order['payment_balance'])
	{
		$data['amount'] = $order['payment_balance'];
	}
	
	// Amount is absolute.
	$data['amount'] = abs($data['amount']);
	
	// Perform action.
	switch ($data['action'])
	{
		case 'charge':
		
			$charge = Stripe_Charge::create(array(
				'amount' => round($data['amount']*100),
				'currency' => $order['billing']['currency'] ?: 'usd',
				'customer' => $customer['id'],
				'description' => 'Order #'.$data['order_id'].': '.$data['reason']
			));
			if ($charge['paid'])
			{
				$data['amount_refundable'] = $data['amount'];
				$data['charge_id'] = $charge['id'];
				$data['status'] = 'success';
			}
			break;

		case 'refund':
		
			if ($data['action'] == 'refund' && !$data['charge_id'])
			{
				throw new Exception('Stripe refund requires charge_id');
			}
			$charge = Stripe_Charge::retrieve($data['charge_id']);
			$charge->refund(array('amount' => round($data['amount']*100)));
			
			// Record as negative number.
			$data['amount'] = -($data['amount']);
			
			// Refund succeeded.
			$data['status'] = 'success';
			
			// Update status of last charge payment.
			$payment = get("/payments/:one", array(
				'order_id' => $data['order_id'],
				'charge_id' => $data['charge_id'],
				'action' => 'charge'
			));
			if ($payment)
			{
				put($payment, array(
					'amount_refundable' => $payment['amount'] - $payment['amount_refunded'] + $data['amount'],
					'amount_refunded' => $payment['amount_refunded'] - $data['amount'],
					'status' => 'refunded'
				));
			}
			break;
			
		default:
			
			throw new Exception("Stripe does not understand this action: {$data['action']}");
	}
	
	return $data;
}