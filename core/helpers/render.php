<?php
/**
 * Render a view.
 *
 *		Usage example:
 *			{render "relative/view/path"}
 *			{render "/absolute/view/path.html"}
 */
function render ($params)
{
	$assign = isset($params['assign']) ? $params['assign'] : true;
	
	return Request::$controller->view->render($params, $assign);
}
