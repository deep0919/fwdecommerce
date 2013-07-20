<?php
/**
 * Refresh original request.
 *
 *		Usage example:
 *			{refresh}
 */
function refresh ()
{
	redirect(array('refresh' => true));
}