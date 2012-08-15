<?php
class User {

protected $screen_name;
protected $name;
protected $id;
protected $hash;
protected $salt;
protected $following;
protected $followers;
protected $isAdmin;

protected function _initalizeUser($user) {
	$this->screen_name = $user{'screen_name'};
	$this->name = $user{'name'};
	$this->id = $user{'id'};
	$this->hash = $user{'hash'};
	$this->salt = $user{'salt'};
	$this->location = $user{'location'};
	$this->url = $user{'url'};
	$this->description = $user{'description'};
	$this->isAdmin = $user{'isAdmin'};
}

public function __construct() {
	$this->screen_name = "";
	$this->id = 0;
	$this->name = "";
	$this->hash = "";
	$this->salt = "";
	$this->location = "";
	$this->url = "";
	$this->description = "";
	$this->isAdmin = 0;;
}

public function lookupByScreenName ($screen_name) {
	global $db;
	$user = $db->query(sprintf("SELECT id,name,screen_name,hash,salt,location,url,description,isAdmin FROM `users` WHERE screen_name='%s';",
		$db->real_escape_string($screen_name)));
	$this->_initalizeUser($user[0]);
}

public function lookupByID ($id) {
	global $db;
	$user = $db->query(sprintf("SELECT id,name,screen_name,hash,salt,location,url,description,isAdmin FROM `users` WHERE id='%d';",
		$db->real_escape_string($id)));
	$this->_initalizeUser($user[0]);
}

public function verifyPassword($password) {
	#print "hash: " . $this->hash . "\n";
	#print "hash: " . hash("sha256", $password . $this->salt) . "\n";
	#exit;
	return ($this->hash == hash("sha256", $password . $this->salt));
}

public function getUser() {
	global $baseurl, $avatardir;
	if ($this->screen_name == "") {
		return; # TODO Nothing?
	}
	return array("id" => $this->id,
		"screen_name" => $this->screen_name,
		"name" => $this->name,
		"location" => $this->location,
		"url" => $this->url,
		"description" => $this->description,
		"profile_image_url" => $baseurl . "/" . $avatardir . "/" . $this->screen_name . ".png"
	);
}

# TODO handle pending friends
public function getFollowers() {
	global $db;
	if (!defined($this->followers))
	foreach ($db->query(sprintf("SELECT follower_id as id FROM `relationships` WHERE following_id='%d';",
		$db->real_escape_string($this->id))) as $follower) {
		$this->followers[] = $follower{'id'};
	}
	return $this->followers;
}

# TODO handle pending friends
public function getFollowing() {
	global $db;
	if (!defined($this->following))
	foreach ($db->query(sprintf("SELECT following_id as id FROM `relationships` WHERE follower_id='%d';",
		$db->real_escape_string($this->id))) as $following) {
		$this->following[] = $following{'id'};
	}
	return $this->following;
}

public function getID() {
	return $this->id;
}

public function setScreenName($screen_name = "") {
	if (empty($screen_name))
		return false;
	return $this->screen_name = $screen_name;
}

public function setName($name = "") {
	if (empty($name))
		return false;
	return $this->name = $name;
}

public function setPassword($password = "") {
	if (empty($password))
		return false;
	$this->salt = mcrypt_create_iv(256, MCRYPT_DEV_URANDOM);
	return $this->hash = hash('sha256', $password . $this->salt);
}

public function setLocation($location = "") {
	if (empty($location))
		return false;
	return $this->location = $location;
}

public function setUrl($url = "") {
	if (empty($url))
		return false;
	return $this->url = $url;
}

public function setDescription($description = "") {
	if (empty($description))
		return false;
	return $this->description = $description;
}

public function save() {
	global $db;

	// set name, location, all that jazz
	if ($this->id) 
		$db->query(sprintf('UPDATE `users` SET
			`hash`="%s", `salt`="%s", url="%s", location="%s", description="%s", screen_name="%s", name="%s"
			WHERE id="%d"',
			$this->hash,
			$db->real_escape_string($this->salt),
			$db->real_escape_string($this->url),
			$db->real_escape_string($this->location),
			$db->real_escape_string($this->description),
			$db->real_escape_string($this->screen_name),
			$db->real_escape_string($this->name),
			$this->id));
	else
		$db->query(sprintf('INSERT INTO `users` (`screen_name`, `hash`, `salt`, `url`, `location`, `description`, `name`) VALUES("%s", "%s", "%s", "%s", "%s", "%s", "%s")',
			$db->real_escape_string($this->screen_name),
			$db->real_escape_string($this->hash),
			$db->real_escape_string($this->salt),
			$db->real_escape_string($this->url),
			$db->real_escape_string($this->location),
			$db->real_escape_string($this->description),
			$db->real_escape_string($this->name) ));
	return true;
}

public function isAdmin() {
	return $this->isAdmin == 1;
}

}
