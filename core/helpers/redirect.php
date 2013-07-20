<?php
/**
 * Redirect request.
 *
 *		Usage example:
 *			{redirect "/home"}
 */
function redirect ($params)
{
	$url = is_string($params) ? $params : $params['to'] ?: $params['url'];
	
	if (!$url && $params['refresh'])
	{
		$url = $_SERVER['REQUEST_URI'];
	}

	return Controller::redirect($url);
}