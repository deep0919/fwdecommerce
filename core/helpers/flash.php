<?php
/**
 * Set a 'flash' message that will persist through a redirect.
 *
 *		Usage example:
 *			{flash error="Uh oh, something went wrong" redirect="/somewhere"}
 *			{flash notice="Account saved!" refresh=true}
 *			{flash warning="There have been {$x} login attempts"}
 */
function flash ($params, $options = null)
{
	if (is_array($params))
	{
		$redirect = $params['redirect'] ?: ($params['refresh'] ? $_SERVER['REQUEST_URI'] : null);
		
		if ($params['error'])
		{
			Controller::error($params['error'], $redirect);
		}
		if ($params['warn'])
		{
			Controller::warn($params['warn'], $redirect);
		}
		if ($params['notice'])
		{
			Controller::notice($params['notice'], $redirect);
		}
	}
	else if (is_string($params))
	{
		Controller::notice($params);
	}
	
	return;
}
