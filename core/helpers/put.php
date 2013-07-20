<?php
/**
 * REST helper: put.
 *
 *		Usage example:
 *			{put [name => "Jane Doe"] "/accounts/123"}
 */
function put ($resource, $data = null)
{
	if (is_array($resource))
	{
		return array(':put' => $resource);
	}
	
	return Controller::__rest('PUT', $resource, $data);
}