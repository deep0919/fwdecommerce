<?php
/**
 * Payments task.
 * Posts auto payment on "pending" orders.
 */

// Get pending orders marked for auto payment.
$pending_orders = get("/orders", array(
	'where' => array(
		'status' => "pending",
		'auto_payment' => true,
		'error' => null
	)
));
foreach ((array)$pending_orders as $order)
{
	// Must be pending, with no prior payments.
	if ($order['status'] != 'pending' || $order['payments'])
	{
		continue;
	}
	
	// Scheduled order?
	if ($order['date_scheduled'])
	{
		// Consider buffer days in schedule.
		$ready_time = time() + ($order['schedule']['buffer']*86400);
		
		// Order not ready yet?
		if (strtotime($order['date_scheduled']) > $ready_time)
		{
			continue;
		}
	}
	
	// Reset result.
	$result = null;
	
	// Payment amount.
	$orig_amount = $amount = total(abs($order['payment_balance']));
	
	// Post credit payment?
	if ($order['credit_total'])
	{
		$result = post("/payments", array(
			'order_id' => $order['id'],
			'method' => 'credit',
			'action' => 'charge',
			'amount' => $order['credit_total'],
			'reason' => 'Automated payment'
		));
		
		// Subtract from remaining amount?
		if (!$result['errors'])
		{
			$amount = $amount - $order['credit_total'];
		}
	}
	
	// Billing method.
	$method = $order['billing']['method'];
	
	// Post billing payment?
	if ($method && $order['billing'][$method] && $amount > 0)
	{
		$result = post("/payments", array(
			'order_id' => $order['id'],
			'method' => $method,
			'action' => 'charge',
			'amount' => $amount,
			'reason' => 'Automated payment'
		));
		
		if ($result['errors']['method'])
		{
			put("/orders/{$order['id']}", array(
				'error' => ucfirst($method)." error: {$result['errors']['method']}",
				'status' => 'hold'
			));
		}
	}
		
	// Payment posted?
	if ($result)
	{
		// Success?
		if (!$result['errors'])
		{
			$request->log[] = "[".time()."] Payment of \${$orig_amount} posted to Order #{$order['id']}";
		}
		else
		{
			$request->log[] = "[".time()."] Error posting payment of \${$orig_amount} to Order #{$order['id']}: ".json_encode($result['errors']);
		}
	}
}


// Get payment settings.
$settings = get("/settings/payments");


/**
* Find and alert overdue invoices?
*/
if ($settings['invoice']['enabled'] && $settings['invoice']['alert_email'])
{
	$overdue_payments = get("/payments", array(
		'where' => array(
			'method' => "invoice",
			'status' => array('$ne' => 'paid'),
			'date_due' => array('$lte' => date('Y-m-d'))
		)
	));
	
	foreach ((array)$overdue_payments as $payment)
	{
		if ($payment['status'] == "overdue")
		{
			// Alert every so often (settings.payments.invoice.alert_frequency).
			$freq = ceil($settings['invoice']['alert_frequency'] ?: 1);
			
			if ($payment['date_alerted'] && time() - strtotime($payment['date_alerted']) < (24*60*60*$freq))
			{
				continue;
			}
			
			$result = post("/emails/overdue", array(
				'payment' => $payment,
				'to' => $settings['invoice']['alert_email']
			));
			
			put($payment, array('date_alerted' => time()));
		}
	}
}