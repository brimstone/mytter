<?php
require_once "api.php";

$matches = array();
preg_match('/^([^&]*)\.([^.&]*)/', $_SERVER{'REDIRECT_QUERY_STRING'}, $matches);
$baseurl = "http" . ($_SERVER{'HTTPS'} == "on" ? "s" : "") . "://" . $_SERVER{'HTTP_HOST'} . dirname($_SERVER{'PHP_SELF'});

$format = $matches[2];

// figure out the api call
switch ($matches[1]) {
	case "account/verify_credentials";
		$ret = verify_credentials();
		break;
	case "account/create";
		$ret = account_create(	isset($_POST['screen_name']) ? $_POST['screen_name'] : "",
					isset($_POST['name']) ? $_POST['name'] : "",
					isset($_POST['location']) ? $_POST['location'] : "",
					isset($_POST['url']) ? $_POST['url'] : "",
					isset($_POST['description']) ? $_POST['description'] : "",
					isset($_POST['password']) ? $_POST['password'] : "",
					$format);
		break;
	case "account/update_profile";
		$ret = update_profile(	isset($_POST['screen_name']) ? $_POST['screen_name'] : "",
					isset($_POST['name']) ? $_POST['name'] : "",
					isset($_POST['location']) ? $_POST['location'] : "",
					isset($_POST['url']) ? $_POST['url'] : "",
					isset($_POST['description']) ? $_POST['description'] : "",
					isset($_POST['password']) ? $_POST['password'] : "",
					"", $format);
		break;
	case "statuses/home_timeline";
		$ret = home_timeline($format);
		break;
	case "statuses/update";
		$ret = update();
		break;
	case "statuses/user_timeline";
		$ret = user_timeline($format);
		break;
	case "statuses/mentions_timeline";
		$ret = mentions($format);
		break;
	case "direct_messages";
		$ret = array();
		break;
	case "friends/ids";
		$ret = friends_ids($format);
		break;
	case "followers/ids";
		$ret = followers_ids($format);
		break;
	case "friendships/exists";
		$ret = friendships_exists(	isset($_GET{'screen_name_a'}) ? $_GET{'screen_name_a'} : "",
						isset($_GET{'screen_name_b'}) ? $_GET{'screen_name_b'} : "",
						isset($_GET{'user_id_a'}) ? $_GET{'user_id_a'} : "",
						isset($_GET{'user_id_b'}) ? $_GET{'user_id_b'} : "");
		break;
	case "friendships/create";
		$ret = friendships_create($format);
		break;
	case "friendships/destroy";
		$ret = friendships_destroy($format);
		break;
	case "users/lookup";
		$ret = user_lookup($format);
		break;
	case "users/show";
		$ret = user_show($format);
		break;
	case "help/test";
		$ret = "v1.1";
		break;
	default;
		$ret = $_SERVER{'REDIRECT_QUERY_STRING'};
}

if (!isset($ret)) {
	header("HTTP/1.0 404 Not Found");
	$ret = array("errors" => array(array("message" => "Sorry, that page does not exist " . $matches[1], "code" => 34)));
}

if (is_string($ret))
	echo "$ret";
else {
	header("Content-type: application/json");
	echo preg_replace('#\\\/#', "/", json_encode($ret));
}
