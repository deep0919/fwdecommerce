<?php
/**
 * Orders task.
 * Posts scheduled / recurring orders.
 */

// Get ready / scheduled / recurring orders.
$recurring_orders = get("/orders", array(
	'where' => array(
		'status' => "ready",
		'schedule' => array('$ne' => null),
		'next_id' => null
	)
));
foreach ((array)$recurring_orders as $order)
{
	// Must be ready.
	if ($order['status'] != 'ready')
	{
		continue;
	}
	
	// Order has recurring schedule?
	if ($order['schedule']['every'])
	{
		// Post a copy of this order?
		if (!$next_order = get("/orders", array('prev_id' => $order['id'])))
		{
			$next_order = post("/orders", array('order' => $order));
		}
		
		// Save next order ID to this order.
		put($order, array('next_id' => $next_order['id']));
	}
}

