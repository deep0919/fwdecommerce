<?php
/**
 * Determine if value A is contained in value B.
 *
 *		Usage example:
 *			{$values = [a, b, c]}
 *			{if a|in:$values} # true
 *			{if x|in:$values} # false
 *			...
 *			{$value = "Hello World"}
 *			{if "Hello"|in:$value} # true
 *			{if "Goodbye"|in:$value} # false
 */
function in ($val_a, $val_b = null)
{
	if (is_scalar($val_a))
	{
		if (is_array($val_b))
		{
			return in_array($val_a, $val_b);
		}
		else if ($val_a && is_scalar($val_b))
		{
			return strpos($val_b, $val_a) !== false;
		}
	}
	else if (is_array($val_a))
	{
		foreach ($val_a as $k => $v)
		{
			if (!in($v, $val_b))
			{
				return false;
			}
			
			return true;
		}
	}
	
	return;
}
