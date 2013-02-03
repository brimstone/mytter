<?php
# load our db stuff
require_once "database.php";
require_once "config.php";
require_once "user.php";
require_once "functions.php";


ob_start();
// timezone crap
$db->query("SET time_zone = '+00:00';");

$baseurl = "http" . ($_SERVER{'HTTPS'} == "on" ? "s" : "") . "://" . $_SERVER{'HTTP_HOST'} . dirname($_SERVER{'PHP_SELF'});

function requireUser() {
	if (!isset($_SERVER{'PHP_AUTH_USER'})) {
		header("HTTP/1.1 401 Unauthorized");
		header("WWW-Authenticate: Basic realm=\"Mytter\"");
		exit();
	}
	$user = new User();
	$user->lookupByScreenName($_SERVER{'PHP_AUTH_USER'});
	if ($user->verifyPassword($_SERVER{'PHP_AUTH_PW'}))
		return $user;
	$user = $user->getUser();
	header("HTTP/1.1 401 Unauthorized");
	header("WWW-Authenticate: Basic realm=\"Mytter\"");
	exit();
}

// actual api call
function verify_credentials() {
	$user = requireUser();
	return $user->getUser();
}

function home_timeline($format = "") {
	global $db, $baseurl, $avatardir;
	$user = requireUser();
	$since_id = 0;
	if (isset($_GET{'since_id'}))
		$since_id = $_GET{'since_id'};

	$count = 100;
	if (isset($_GET['count']))
		$count = $_GET['count'];
	if (isset($_POST['count']))
		$count = $_POST['count'];

	$rawtimeline = $db->query(sprintf("SELECT	DISTINCT
						`updates`.`id` as id,
						`updates`.`text` as text,
						UNIX_TIMESTAMP(`updates`.`created`) as created_at,
						`updates`.`user_id` as user_id,
						`users`.`screen_name` as screen_name,
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
						$db->real_escape_string($user->getID()),
						$db->real_escape_string($user->getID()),
						$since_id,
						$db->real_escape_string($count)));
	$timeline = array();
	if (is_array($rawtimeline))
		foreach ($rawtimeline as $x) {
			$u = new User();
			$u->lookupByScreenName($x{'screen_name'});
			$timeline[] = array("id" => $x{'id'},
				"text" => $x{'text'},
				"created_at" => format_time($x{'created_at'}),
				"entities" => array("urls" => array(), "hashtags" => array(), "user_mentions" => array()),
				"user" => $u->getUser(),
				"favorited" => false,
				"source" => "blah"
			);
		}

	if (empty($timeline) and isset($_GET['stream']) and $_GET['stream'] == "true") {
		#generate random queue
		$queueid = mt_rand();
		while(!$db->query(sprintf("INSERT INTO `queues` (`queue`, `screen_name_id`) VALUES ('%d', '%d');", $db->real_escape_string($queueid), $db->real_escape_string($user->getID())))) {
			$queueid = mt_rand();
		}
		# create our queue and listen to it
		$msgqueue = msg_get_queue($queueid, 0600);
		msg_receive($msgqueue, 1, $msg_type, 16384, $msg);
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

function user_timeline($format = "") {
	global $db, $baseurl, $avatardir;

	// figure out our paramaters
	$user = new User();
	if (isset($_GET{'user_id'}))
		$user->lookupByID($_GET{'user_id'});
	if (isset($_GET{'screen_name'}))
		$user->lookupByScreenName($_GET{'screen_name'});

	if ($user->isProtected()) {
		$requestor = requireUser();
		if (!friendships_exists("", "", $requestor->getID(), $user->getID())){
			return "You're not friends bro" . $requestor->getID();
		}
	}

	$since_id = 0;
	if (isset($_GET{'since_id'}))
		$since_id = $_GET{'since_id'};

	$count = 20;
	if (isset($_GET['count']))
		$count = $_GET['count'];
	if (isset($_POST['count']))
		$count = $_POST['count'];

	// TODO figure out private users
	// figure out friends
	$rawtimeline = $db->query(sprintf("SELECT
						`updates`.`id` as id,
						`updates`.`text` as text,
						UNIX_TIMESTAMP(`updates`.`created`) as created_at,
						`updates`.`user_id` as user_id
						FROM `updates`
						WHERE `updates`.`user_id` = '%d'
							AND `updates`.`id` > %d
						ORDER BY `updates`.`id` DESC
						LIMIT %d
						",
						$db->real_escape_string($user->getID()),
						$since_id,
						$db->real_escape_string($count)));
	$timeline = array();
	if (is_array($rawtimeline))
		foreach ($rawtimeline as $x) {
			$timeline[] = array("id" => $x{'id'},
				"text" => $x{'text'},
				"created_at" => format_time($x{'created_at'}),
				"entities" => array("urls" => array(), "hashtags" => array(), "user_mentions" => array()),
				"source" => "blah",
				"user" => $user->getUser()
			);
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
	$user = requireUser();
	$status = "";
	if (isset($_POST{'status'}))
		$status = $_POST{'status'};
	else if (isset($_GET{'status'}))
		$status = $_GET{'status'};

	if ($status != "") {
		$db->query(sprintf("INSERT INTO `updates` (`user_id`, `text`, `created`) VALUES ('%d', '%s', NOW());", $db->real_escape_string($user->getID()), $db->real_escape_string($status)));
		# i know, it's bad to assume it worked FIXME
		# get list of queues
		$queues = $db->query(sprintf("SELECT q.queue as queue FROM queues as q, relationships as r WHERE '%d' = r.following_id and r.follower_id = q.screen_name_id;", $db->real_escape_string($user->getID())));
		# try to send to each of them
		if (is_array($queues))
			foreach ($queues as $queue) {
				$msgqueue = msg_get_queue($queue{'queue'}, 0600);
				msg_send($msgqueue, 1, "msg", true, true, $msg_err);
				# remove old queue from db
				$db->query("DELETE FROM `queues` WHERE queue=" . $queue{'queue'});
				# destroy queue
				msg_remove_queue($msgqueue);
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
	$user = requireUser();
	$friends = $db->query(sprintf("SELECT following_id as id FROM relationships WHERE follower_id='%d'", $db->real_escape_string($user->getID())));
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

function followers_ids($format = "") {
	global $db;
	$user = requireUser();
	$friends = $db->query(sprintf("SELECT follower_id as id FROM relationships WHERE following_id='%d'", $db->real_escape_string($user->getID())));
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

function friendships_exists($screen_name_a = "",
                            $screen_name_b = "",
                            $user_id_a = "",
                            $user_id_b = "") {
	global $db;

	# setup our user variables
	$user_a = new User();
	$user_b = new User();
	# figure out which user is which
	if ($screen_name_a != "")
		$user_a->lookupByScreenName($screen_name_a);
	if ($screen_name_b != "")
		$user_b->lookupByScreenName($screen_name_b);
	# TODO something here to not lookup if we don't need to
	if ($user_id_a != "")
		$user_a->lookupByID($user_id_a);
	if ($user_id_b != "")
		$user_b->lookupByID($user_id_b);

	if ($user_a->isValid() and $user_b->isValid())
		# simple array check
		return in_array($user_b->getID(), $user_a->getFollowing());
	return false;
}

# FIXME untested
function friendships_create($format) {
	global $db;
	# require our requester to be a user
	$user = requireUser();
	$user_b = new User();
	# figure out the user we're trying to friend
	if (isset($_POST{'screen_name'}))
		$user_b->lookupByScreenName($_POST{'screen_name'});
	if (isset($_POST{'user_id'}))
		$user_b->lookupByID($_POST{'user_id'});
  
	if(friendships_exists("", "", $user->getID(), $user_b->getID())) {
		# return 403 as we're already friends
		header("HTTP/1.0 403 Forbidden");
		return "already friends";
	}
	# TODO check to see if the new friend requires approval
	$db->query(sprintf("INSERT INTO relationships (follower_id, following_id) VALUES ('%d', '%d')", $db->real_escape_string($user->getID()), $db->real_escape_string($user_b->getID()))); 
	# return our new friend
	return $user_b->getUser();
}
function friendships_destroy($format) {
	global $db;
	# require our requester to be a user
	$user = requireUser();
	$user_b = new User();
	# figure out the user we're trying to friend
	if (isset($_POST{'screen_name'}))
		$user_b->lookupByScreenName($_POST{'screen_name'});
	if (isset($_POST{'user_id'}))
		$user_b->lookupByID($_POST{'user_id'});
  
	if(! friendships_exists("", "", $user->getID(), $user_b->getID())) {
		# return 403 as we're already friends
		header("HTTP/1.0 403 Forbidden");
		return "not friends";
	}
	# TODO check to see if the new friend requires approval
	$db->query(sprintf("DELETE FROM relationships WHERE follower_id = '%d' AND following_id = '%d'", $db->real_escape_string($user->getID()), $db->real_escape_string($user_b->getID()))); 
	# return our new friend
	return $user_b->getUser();

}

function mentions($format = "") {
	if ($format == "xml") {
		header("Content-type: application/xml");
		return xml_encode(array(), "statuses");
	}
	return array();
}

function user_lookup($format = "") {
	global $db;
	if ($format == "xml") {
		header("Content-type: application/xml");
		$output = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?".">\n";
		$output .= "<users>\n";
	}
	$users = explode(",", $_POST{'user_id'});
	foreach($users as $uid) {
		$user = $db->query(sprintf("SELECT id,name,screen_name FROM `users` WHERE id='%d';", $db->real_escape_string($uid)));
		if ($format == "xml") {
			$output .= xml_encode($user[0], "user");
		}
	}
	if ($format == "xml") {
		$output .= "</users>\n";
		return $output;
	}
}

function user_show($format = "") {
	global $db;
	$user = new User;
	if (isset($_GET{'user_id'}))
		$user->lookupByID($_GET{'user_id'});
	if (isset($_GET{'screen_name'}))
		$user->lookupByScreenName($_GET{'screen_name'});
	// TODO if this isn't valid, return an error
	if (!$user->isValid())
		return "Not a valid user";
	$toreturn = $user->getUser();
	$out = $db->query(sprintf("SELECT count(*) FROM `updates` WHERE user_id='%d';", $toreturn{'id'}));
	$toreturn{'statuses_count'} = $out[0]['count(*)'];
	$out = $db->query(sprintf("SELECT count(*) FROM `relationships` WHERE follower_id='%d';", $toreturn{'id'}));
	$toreturn{'friends_count'} = $out[0]['count(*)'];
	$out = $db->query(sprintf("SELECT count(*) FROM `relationships` WHERE following_id='%d';", $toreturn{'id'}));
	$toreturn{'followers_count'} = $out[0]['count(*)'];;
	// TODO Make favourites_count real if we want
	$toreturn{'favourites_count'} = 42;
	// TODO if user is protected, don't include the most recent status
	if (!$user->isProtected()) {
		$toreturn{'text'} = "";
	}
	// TODO if we're authenticated and setup to follow this person
	// $toreturn{'following'} = true
	return $toreturn;
}


function account_create($screen_name = "", $name = "", $location = "", $url = "", $description = "", $password = "", $format = "") {
	// verify user
	$user = requireUser();
	// make sure user is admin if we require it
	if (!$user->isAdmin())
		return "nope";
	// create new user object
	$newuser = new User();
	// see if our requested username is available
	if ($newuser->lookupByScreenName($screen_name))
		return "fail";
	// pass off our new user and stuff to update_profile
	return update_profile($screen_name, $name, $location, $url, $description, $password, $newuser, $format);
}

function update_profile($screen_name = "", $name = "", $location = "", $url = "", $description = "", $password = "", $user = "", $format = "") {
	// validate account if $user is unset
	if (empty($user))
		$user = requireUser();
	// run query to update thing
	if (!empty($screen_name))
		$user->setScreenName($screen_name);
	if (!empty($name))
		$user->setName($name);
	if (!empty($location))
		$user->setLocation($location);
	if (!empty($url))
		$user->setUrl($url);
	if (!empty($description))
		$user->setUrl($description);
	if (!empty($password))
		$user->setPassword($password);
	if (!$user->save())
		return "There was an error saving"; // TODO this should probably call an error() function
	return $user->getUser();
}

function save_password($screen_name = "", $password = "") {
	if (empty($password))
		return false;
	$user = requireUser();
	if ($user->isAdmin()) {
		$user->lookupByScreenName($screen_name);
	}
	return $user->savePassword($password);
}
