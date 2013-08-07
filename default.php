<?php
require_once "api.php";

$matches = array();
preg_match('/^([^&]*)\.([^.&]*)/', $_SERVER{'REDIRECT_QUERY_STRING'}, $matches);
$baseurl = "http" . ($_SERVER{'HTTPS'} == "on" ? "s" : "") . "://" . $_SERVER{'HTTP_HOST'} . dirname($_SERVER{'PHP_SELF'});

$format = $matches[2];

// figure out the api call
switch ($matches[1]) {
	case "help/test";
		$ret = "default";
		break;
	default;
		// TODO check to see if this is a user on our system
		// TODO redirect or ostatus or something
		$ret = "I don't know how to handle " . $_SERVER{'REDIRECT_QUERY_STRING'};
}

if (!isset($ret)) {
	header("HTTP/1.0 404 Not Found");
	$ret = array("errors" => array(array("message" => "Sorry, that page does not exist " . $matches[1], "code" => 34)));
}

if (is_string($ret))
	echo "$ret";
else {
	header("Content-type: application/json");
	echo json_encode($ret);
}
