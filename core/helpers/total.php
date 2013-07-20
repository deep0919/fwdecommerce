<?php
/**
 * Format a total value as a string with precision.
 * To format a localized money string, use money() instead.
 *
 * 		Usage example:
 *			{$price = 10}
 *			{$price|total} # 10.00
 */
function total ($params, $negative = false)
{
	$amount = is_array($params) ? $params['amount'] : $params;
	$negative = is_array($params) ? $params['negative'] ?: $negative : $negative;
	
	// Allow negative?
	$amount = ($negative || $amount > 0) ? $amount : 0;
	
	// Strip formatting.
	$amount = str_replace(',', '', $amount);
	
	return number_format($amount, 2, '.', '');
}
