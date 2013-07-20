<?php
/**
 * Camelize a string.
 * 
 *		Usage example:
 *			{"long-name-for-example"|camelize} # LongNameForExample
 */
function camelize ($params)
{
	if (is_string($params))
	{
		$word = $params;
	}
	else if (!$word = $params['word'])
	{
		return false;
	}

	return str_replace(' ', '', ucwords(strtolower(preg_replace('/[-_]/', ' ', $word))));
}
