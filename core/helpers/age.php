<?php
/**
 * Returns the age of a date/time.
 *
 *		Usage example:
 *			{$order.date_created|age} # 27 minutes ago
 */
function age ($params, $delay = 0)
{
	$date = is_array($params) && $params['of'] ? $params['of'] : $params;

	// Make sure we have a timestamp.
	$time = is_numeric($date) ? (int)$date : strtotime($date);

	$seconds_elapsed = (time() - $time - $delay);

	// Seconds.
	if ($seconds_elapsed < 60)
	{
		return 'just now';
	}
	// Minutes.
	else if ($seconds_elapsed >= 60 && $seconds_elapsed < 3600)
	{
		$age = floor($seconds_elapsed / 60).' '.pluralize(array('word' => 'minute', 'if_many' => floor($seconds_elapsed / 60)));
	}
	// Hours.
	else if ($seconds_elapsed >= 3600 && $seconds_elapsed < 86400)
	{
		$age = floor($seconds_elapsed / 3600).' '.pluralize(array('word' => 'hour', 'if_many' => floor($seconds_elapsed / 3600)));
	}
	// Days.
	else if ($seconds_elapsed >= 86400 && $seconds_elapsed < 604800)
	{
		$age = floor($seconds_elapsed / 86400).' '.pluralize(array('word' => 'day', 'if_many' => floor($seconds_elapsed / 86400)));
	}
	// Weeks.
	else if ($seconds_elapsed >= 604800 && $seconds_elapsed < 2626560)
	{
		$age = floor($seconds_elapsed / 604800).' '.pluralize(array('word' => 'week', 'if_many' => floor($seconds_elapsed / 604800)));
	}
	// Months.
	else if ($seconds_elapsed >= 2626560 && $seconds_elapsed < 31536000)
	{
		$age = floor($seconds_elapsed / 2626560).' '.pluralize(array('word' => 'month', 'if_many' => floor($seconds_elapsed / 2626560)));
	}
	// Years.
	else if ($seconds_elapsed >= 31536000)
	{
		$age = floor($seconds_elapsed / 31536000).' '.pluralize(array('word' => 'year', 'if_many' => floor($seconds_elapsed / 31536000)));
	}

	return "{$age} ago";
}
