<?php
/**
 * Dump a variable.
 * Useful in debugging.
 *
 *		Usage example:
 *			{"/channels/blog/entries"|get|dump}
 */
function dump ($var)
{
	if ($var instanceof ArrayInterface)
	{
		$dump = $var->dump(true);
	}
	else
	{
		$dump = print_r($var, true);
	}
	
	print '<pre class="prettyprint linenums">'.htmlspecialchars($dump).'</pre>';
}