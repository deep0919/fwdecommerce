<?php
/**
 * REST helper: delete.
 *
 *		Usage example:
 *			{delete "/accounts/123"}
 */
function delete ($resource)
{
	return Controller::__rest('DELETE', $resource);
}