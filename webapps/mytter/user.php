<?php
class User {

protected $screen_name;
protected $name;
protected $id;
protected $passwordhash;
protected $passwordsalt;

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
	$this->screen_name = $user[0]{'screen_name'};
	$this->passwordhash = $user[0]{'password'};
	$this->id = $user[0]{'id'};
	$this->name = $user[0]{'name'};
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
public function getID() {
	return $this->id;
}

}
