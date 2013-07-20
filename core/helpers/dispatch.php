<?php
/**
 * Dispatch a request.
 *
 *		Usage example:
 *			{dispatch "/some/request"}
 */
function dispatch ($params, $render = true)
{
	return Request::dispatch($params, $render);
}