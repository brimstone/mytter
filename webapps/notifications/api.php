<?php
# load our db stuff
require "config.php";

function query($query = null) {
	global $db;
	if (!$results = $db->query($query))
		die("You fucked up.<br/>" . $db->error);
	if ($results->num_rows == 0)
		return;
	$toreturn = array();
	while($result = $results->fetch_assoc())
		$toreturn[] = $result;
	$results->free();
	return $toreturn;
}

function format_time($time) {
	$dtzone = new DateTimeZone("GMT");
	$dtime = new DateTime();
	$dtime->setTimestamp($time);
	$dtime->setTimeZone($dtzone);
	return $dtime->format("D M d H:i:s O Y");
}

// http://davidwalsh.name/watch-post-save-php-post-data-xml
function xml_encode($arr, $wrapper = 'data', $cycle=1) {
	//useful vars
	$new_line = "\n";

	//start building content
	$output= '<'.$wrapper.'>'.$new_line;
	foreach($arr as $key => $val)
	{
		if(!is_array($val))
		{
			$output.= '<'.htmlspecialchars($key).'>'.$val.'</'.htmlspecialchars($key).'>'.$new_line;
		}
		else
		{
			$output.= xml_encode($val,$key,$cycle + 1).$new_line;
		}
	}
	$output.= '</'.$wrapper.'>';

	//return the value
	return $output;
}

$matches = array();
preg_match('/^([^&]*)\.([^.&]*)/', $_SERVER{'REDIRECT_QUERY_STRING'}, $matches);

// timezone crap
$db->query("SET time_zone = '+00:00';");


// figure out the api call
switch ($matches[1]) {
	case "account/verify_credentials";
		$ret = verify_credentials();
		break;
	case "statuses/home_timeline";
		$ret = home_timeline($matches[2]);
		break;
	case "statuses/update";
		$ret = update();
		break;
	case "statuses/mentions";
		$ret = mentions($matches[2]);
		break;
	case "friends/ids";
		$ret = friends_ids($matches[2]);
		break;
	case "users/lookup";
		$ret = user_lookup($matches[2]);
		break;
}

if (is_string($ret))
	echo "$ret";
else {
	header("Content-type: application/json");
	echo json_encode($ret);
}

// generic function to check user
function check_user () {
	global $db;
	if (!isset($_SERVER{'PHP_AUTH_USER'})) {
		header("HTTP/1.1 401 Unauthorized");
		header("WWW-Authenticate: Basic realm=\"Notification daemon\"");
		exit();
	}
	$user = query(sprintf("SELECT id,name,screen_name FROM `users` WHERE screen_name='%s' AND password='%s';", $db->real_escape_string($_SERVER{'PHP_AUTH_USER'}), $db->real_escape_string($_SERVER{'PHP_AUTH_PW'})));
	if ($user[0]{'screen_name'} !== $_SERVER{'PHP_AUTH_USER'}) {
		header("HTTP/1.1 401 Unauthorized");
		header("WWW-Authenticate: Basic realm=\"Notification daemon\"");
		exit();
	}
	return $user[0];
}

// actual api call
function verify_credentials() {
	$user = check_user();
	return $user;
}

function home_timeline($format = "") {
	global $db;
	$user = check_user();
	$since_id = 0;
	if (isset($_GET{'since_id'}))
		$since_id = $_GET{'since_id'};

	$count = 100;
	if (isset($_GET['count']))
		$count = $_GET['count'];
	if (isset($_POST['count']))
		$count = $_POST['count'];

	$rawtimeline = query(sprintf("SELECT	DISTINCT
						`updates`.`id` as id,
						`updates`.`text` as text,
						UNIX_TIMESTAMP(`updates`.`created`) as created_at,
						`updates`.`user_id` as user_id,
						`users`.`screen_name` as screen_name,
						`users`.`profile_image_url` as profile_image_url,
						`users`.`name` as name
						FROM `relationships`, `updates`, `users`
						WHERE users.id = updates.user_id AND (
							(relationships.follower_id = '%d' AND relationships.following_id = updates.user_id)
							OR updates.user_id = '%d'
							)
							AND updates.id > '%d'
						ORDER BY `updates`.`id` DESC
						LIMIT %d
						",
						$db->real_escape_string($user{'id'}),
						$db->real_escape_string($user{'id'}),
						$since_id,
						$db->real_escape_string($count)));
	$timeline = array();
	if (!empty($rawtimeline))
		foreach ($rawtimeline as $x) {
			$timeline[] = array("id" => $x{'id'},
				"text" => $x{'text'},
				"created_at" => format_time($x{'created_at'}),
				"entities" => array("urls" => array(), "hashtags" => array(), "user_mentions" => array()),
				"user" => array("id" => $x{'user_id'}, "screen_name" => $x{'screen_name'}, "name" => $x{'name'}, "profile_image_url" => "https://the.narro.ws/notifications/avatars/" . $x{'profile_image_url'}),
				"favorited" => false,
				"source" => "blah"
			);
		}

	if (empty($timeline) and isset($_GET['stream']) and $_GET['stream'] == "true") {
		#generate random queue
		$queueid = mt_rand();
		while(!$db->query(sprintf("INSERT INTO `queues` (`queue`, `screen_name_id`) VALUES ('%d', '%d');", $db->real_escape_string($queueid), $db->real_escape_string($user{'id'})))) {
			$queueid = mt_rand();
		}
		# create our queue and listen to it
		$msgqueue = msg_get_queue($queueid, 0600);
		msg_receive($msgqueue, 1, $msg_type, 16384, $msg);
		$db->query("DELETE FROM `queues` WHERE queue=$queueid");
		return home_timeline($format);
	}
	if ($format == "xml") {
		header("Content-type: application/xml");
		$toreturn = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?".">\n";
		$toreturn .= "<statuses>\n";
		foreach ($timeline as $item)
			$toreturn .= xml_encode($item, "status");
		$toreturn .= "</statuses>";
		return $toreturn;
	}
	return $timeline;
}

function update($format = "") {
	global $db;
	$user = check_user();
	$status = "";
	if (isset($_POST{'status'}))
		$status = $_POST{'status'};
	else if (isset($_GET{'status'}))
		$status = $_GET{'status'};

	if ($status != "") {
		$db->query(sprintf("INSERT INTO `updates` (`user_id`, `text`, `created`) VALUES ('%d', '%s', NOW());", $db->real_escape_string($user{'id'}), $db->real_escape_string($status)));
		# i know, it's bad to assume it worked FIXME
		# get list of queues
		$queues = query(sprintf("SELECT q.queue as queue FROM queues as q, relationships as r WHERE '%d' = r.following_id and r.follower_id = q.screen_name_id;", $db->real_escape_string($user{'id'})));
		# try to send to each of them
		foreach ($queues as $queue) {
			$msgqueue = msg_get_queue($queue{'queue'}, 0600);
			msg_send($msgqueue, 1, "msg", true, true, $msg_err);
		}
	}

	$update = array("text" => $status);

	if ($format == "xml") {
		header("Content-type: application/xml");
		return "<?xml version=\"1.0\" encoding=\"UTF-8\" ?".">\n" .
			xml_encode($update, "update");
	}
	return $update;
}

function friends_ids($format = "") {
	global $db;
	$user = check_user();
	$friends = query(sprintf("SELECT following_id as id FROM relationships WHERE follower_id='%d'", $db->real_escape_string($user{'id'})));
	if ($format == "xml") {
		header("Content-type: application/xml");
		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?".">\n";
		$output .= "<id_list>\n";
		$output .= "<ids>\n";
		foreach($friends as $x)
			$output .= "<id>" . $x{'id'} . "</id>\n";
		$output .= "</ids>\n";
		$output .= "<next_cursor>0</next_cursor><previous_cursor>0</previous_cursor>\n";
		$output .= "</id_list>\n";
		return $output;
	}
	$toreturn = array("previous_cursor" => 0, "ids" => array(), "previous_cursor_str" => "0", "next_cursor" => 0, "next_cursor_str" => "0");
	foreach($friends as $x)
		$toreturn{'ids'}[] = $x{'id'};
	return $toreturn;
}

function mentions($format = "") {
	if ($format == "xml") {
		header("Content-type: application/xml");
		return xml_encode(array(), "statuses");
	}
	return array();
}

function user_lookup($format = "") {
#$log=fopen("/tmp/twitter.log", "a");
#fwrite($log, print_r($_POST, true));
#fclose($log);
	global $db;
	if ($format == "xml") {
		header("Content-type: application/xml");
		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
		$output .= "<users>\n";
	}
	$users = explode(",", $_POST{'user_id'});
	foreach($users as $uid) {
		$user = query(sprintf("SELECT id,name,screen_name FROM `users` WHERE id='%d';", $db->real_escape_string($uid)));
		if ($format == "xml") {
			$output .= xml_encode($user[0], "user");
		}
	}
	if ($format == "xml") {
		$output .= "</users>\n";
		return $output;
	}
}
