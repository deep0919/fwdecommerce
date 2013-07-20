<?php
/*
* Application start.
*/

// This must be set to the folder containing framework files!
define('APP_ROOT', dirname(dirname(__FILE__)).'/');

// Require core Request object.
require(APP_ROOT.'core/library/Request.php');
//echo "Hello world";
// Start, dispatch request.
Request::start();
