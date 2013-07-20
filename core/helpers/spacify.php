<?php
/**
 * Spacify a string.
 *
 *		Usage example:
 *			{"long-name-for-example"|spacify} # Long Name For Example
 */
function spacify ($params)
{
	if (is_string($params))
	{
		$word = $params;
	}
	else if (!$word = $params['word'])
	{
		return false;
	}
	
	return str_replace('-', ' ', str_replace('_', ' ', $word));
}
