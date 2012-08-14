<?php
class User {

protected $screen_name;
protected $name;
protected $id;
protected $passwordhash;
protected $passwordsalt;
protected $following;
protected $followers;

protected function _initalizeUser($user) {
	$this->screen_name = $user{'screen_name'};
	$this->passwordhash = $user{'password'};
	$this->id = $user{'id'};
	$this->name = $user{'name'};
}

public function __construct() {
	$this->screen_name = "";
	$this->id = 0;
	$this->realname = "";
	$this->passwordhash = "";
	$this->passwordsalt = "";
}

public function lookupByScreenName ($screen_name) {
	global $db;
	$user = $db->query(sprintf("SELECT id,name,screen_name,password FROM `users` WHERE screen_name='%s';", $db->real_escape_string($screen_name)));
	$this->_initalizeUser($user[0]);
}

public function lookupByID ($id) {
	global $db;
	$user = $db->query(sprintf("SELECT id,name,screen_name,password FROM `users` WHERE id='%d';", $db->real_escape_string($id)));
	$this->_initalizeUser($user[0]);
}

public function verifyPassword($password) {
	return ($password == $this->passwordhash);
}

public function getUser() {
	global $baseurl, $avatardir;
	if ($this->screen_name == "") {
		return; # TODO Nothing?
	}
	return array("id" => $this->id,
		"screen_name" => $this->screen_name,
		"name" => $this->name,
		"profile_image_url" => $baseurl . "/" . $avatardir . "/" . $this->screen_name . ".png"
	);
}

# TODO handle pending friends
public function getFollowers() {
	global $db;
	if (!defined($this->followers))
	foreach ($db->query(sprintf("SELECT follower_id as id FROM `relationships` WHERE following_id='%d';", $db->real_escape_string($this->id))) as $follower) {
		$this->followers[] = $follower{'id'};
	}
	return $this->followers;
}

# TODO handle pending friends
public function getFollowing() {
	global $db;
	if (!defined($this->following))
	foreach ($db->query(sprintf("SELECT following_id as id FROM `relationships` WHERE follower_id='%d';", $db->real_escape_string($this->id))) as $following) {
		$this->following[] = $following{'id'};
	}
	return $this->following;
}

public function getID() {
	return $this->id;
}

public function save() {
	// set name, location, all that jazz
	if ($this->id) 
		$db->query(sprintf("UPDATE `users` WHERE id='%d'...", $this->id, ...)); // TODO
	else
		$db->query(sprintf("INSERT INTO `users` ..", $this->name, ...)); // TODO
}

}
