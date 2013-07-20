<?php
/**
 * Returns the age of a date/time,
 * or the date if it is outside of 'today'.
 *
 *		Usage example:
 *			{$account.date_created|age_date} # 23 hours ago
 *			{$product.date_created|age_date} # Dec 25 2012
 */
function age_date ($params)
{
	$date = is_array($params) && $params['of'] ? $params['of'] : $params;
	
	if (!$time = strtotime($date))
	{
		return '';
	}

	// Today.
	if (date('Y-m-d') == date('Y-m-d', $time))
	{
		return age($date);
	}
	
	if (date('Y') == date('Y', $time))
	{
		return date('M j', $time);
	}
	else
	{
		return date('M j Y', $time);
	}
}
