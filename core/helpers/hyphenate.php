<?php
/**
 * Hyphenate a string.
 * 
 *		Usage example:
 *			{"Long Name For Example"|hyphenate} # long-name-for-example
 */
function hyphenate ($params)
{
	if (is_string($params))
	{
		$word = $params;
	}
	else if (!$word = $params['word'])
	{
		return false;
	}
	
	$word = trim($word);
	$word = preg_replace('/[^a-zA-Z0-9\-\_\s]/', '', $word);
	$word = preg_replace('/[\_\s\-]+/', '-', $word);
	$word = preg_replace('/([a-z])([A-Z])/', '\\1-\\2', $word);
	$word = strtolower($word);
	
	return $word;
}
