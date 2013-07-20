<?php
/**
 * Order an array by index.
 * Default ascending. Prefix with "!" for descending.
 *
 * 		Usage example:
 *			{foreach $users|orderby:"name" as $user}
 *				...
 *			{/foreach}
 */
function orderby ($array)
{
	if ($array instanceof ModelCollection)
	{
		$collection = $array;
		$array = $collection->records();
	}
	elseif (!is_array($array))
	{
		return false;
	}
	
	$args = func_get_args();
	array_shift($args);
	
	$sorter = function ($a, $b = null)
	{
		static $args;
		
		if ($b == null)
		{
			$args = $a;
			return;
		}
		
		foreach ((array)$args as $k)
		{
			if ($k[0] == '!')
			{ 
				$k = substr($k, 1); 
				
				if ($a[$k] === "" || $a[$k] === null)
				{
					return 0;
				}
				else if (is_numeric($b[$k]) && is_numeric($a[$k]))
				{
					return $a[$k] < $b[$k];
				}
				
				return strnatcmp(@$a[$k], @$b[$k]); 
			} 
			else
			{
				if ($b[$k] === "" || $b[$k] === null)
				{
					if ($a[$k] === "" || $a[$k] === null)
					{
						return 0;
					}
					return -1;
				}
				else if (is_numeric($b[$k]) && is_numeric($a[$k]))
				{
					return $a[$k] > $b[$k];
				}
				
				return strnatcmp(@$b[$k], @$a[$k]); 
			} 
		}
		
		return 0; 
	};

	$sorter($args);
	
	$array = array_reverse($array, true);
	uasort($array, $sorter);
	
	return $array;
}
