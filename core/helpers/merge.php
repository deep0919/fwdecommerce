<?php
/**
 * Merge two indexed arrays recursively.
 * 
 *		Usage example:
 *			{$set1 = [a => [b => c], x => y]}
 *			{$set2 = [a => [b => d]]}
 *			{$result = $set1|merge:$set2} # [a => [b => d], x => y]
 */
function merge ($set1, $set2)
{
	$merged = $set1;

	if (is_array($set2) || $set2 instanceof ArrayIterator)
	{
		foreach ($set2 as $key => &$value)
		{
			if ((is_array($value) || $value instanceof ArrayIterator) && (is_array($merged[$key]) || $merged[$key] instanceof ArrayIterator))
			{
				$merged[$key] = merge($merged[$key], $value);
			}
			elseif (isset($value) && !(is_array($merged[$key]) || $merged[$key] instanceof ArrayIterator))
			{
				$merged[$key] = $value;
			}
		}
	}

	return $merged;
}