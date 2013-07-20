<?php
/**
 * Pluralize a string.
 * Converts a word to english plural form, depending on 'if_many' value.
 *
 *		Usage example:
 *			{pluralize "{$items|count} items"} # 1 item
 *			{pluralize "{$items|count} items"} # 10 items
 *			{pluralize "Person"} # People
 *			{pluralize word="Category" if_many=$categories} # Categories
 */
function pluralize ($params)
{
	if (is_string($params))
	{
		$word = $params;
	}
	else if (!$word = $params['word'])
	{
		return false;
	}

	// Conditional.
	if (isset($params['if_many']))
	{
		$if_many = (is_array($params['if_many'])) ? count($params['if_many']) : $params['if_many'];
	}
	else if (is_numeric($word[0]))
	{
		$parts = explode(' ', $word);
		$word = array_pop($parts);
		$if_many = $parts[0];
		$prefix = implode(' ', $parts).' ';
	}

	if (isset($if_many) && $if_many == 1)
	{
		$word = singularize($word);
	}
	else
	{
		$plural = array(
			'/(quiz)$/i' => '\1zes',
			'/^(ox)$/i' => '\1en',
			'/([m|l])ouse$/i' => '\1ice',
			'/(matr|vert|ind)ix|ex$/i' => '\1ices',
			'/(x|ch|ss|sh)$/i' => '\1es',
			'/([^aeiouy]|qu)y$/i' => '\1ies',
			'/(hive)$/i' => '\1s',
			'/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
			'/sis$/i' => 'ses',
			'/([ti])um$/i' => '\1a',
			'/(buffal|tomat)o$/i' => '\1oes',
			'/(bu)s$/i' => '\1ses',
			'/(alias|status)/i'=> '\1es',
			'/(octop|vir)us$/i'=> '\1i',
			'/(ax|test)is$/i'=> '\1es',
			'/s$/i'=> 's',
			'/$/'=> 's'
		);
	
		$irregular = array(
			'person' => 'people',
			'man' => 'men',
			'child' => 'children',
			'sex' => 'sexes',
			'move' => 'moves'
		);
		
		$ignore = array(
			'equipment',
			'information',
			'rice',
			'money',
			'species',
			'series',
			'fish',
			'sheep',
			'data'
		);	
	
		$lower_word = strtolower($word);
		foreach ($ignore as $ignore_word)
		{
			if (substr($lower_word, (-1 * strlen($ignore_word))) == $ignore_word)
			{
				return $prefix.$word;
			}
		}
	
		foreach ($irregular as $_plural=> $_singular)
		{
			if (preg_match('/('.$_plural.')$/i', $word, $arr))
			{
				return $prefix.preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $word);
			}
		}
	
		foreach ($plural as $rule => $replacement)
		{
			if (preg_match($rule, $word))
			{
				return $prefix.preg_replace($rule, $replacement, $word);
			}
		}
	}

	return $prefix.$word;
}
