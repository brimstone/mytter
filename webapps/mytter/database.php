<?php
class DB {

protected $conn;
public function __construct($host, $user, $pass, $database) {
	$this->conn = mysqli_connect($host, $user, $pass, $database);
}

public function query($query = null) {
	$results = mysqli_query($this->conn, $query);
	if (!$results)
		die("You fucked up.<br/>\n" . mysqli_error($this->conn));
	if ($results === true || mysqli_num_rows($results) == 0)
		return true;
	$toreturn = array();
	while($result = mysqli_fetch_assoc($results))
		$toreturn[] = $result;
	return $toreturn;
}

public function real_escape_string($mixed) {
	return mysqli_real_escape_string($this->conn, $mixed);
}

}
