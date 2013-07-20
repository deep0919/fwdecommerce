<?php
/**
 * REST helper: post.
 *
 *		Usage example:
 *			{post [email => "user@example.com"] "/accounts"}
 */
function post ($resource, $data = null)
{
	return Controller::__rest('POST', $resource, $data);
}