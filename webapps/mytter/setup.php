<?
require "database.php";
require "user.php";

##################################################
#   This will probably be passed in by the gui
##################################################
$server = 'localhost';
$database     = 'mytter';
$dbuser   = 'root';
$dbpass   = 'password';
$avatar = 'avatars';
$screenname = 'admin';
$username = 'admin';
$password = 'testing';
##################################################

# Test that the db vars are good
testDB($server, $dbuser, $dbpass);

# Write the config file based off the sample
writeConfig($server, $database, $dbuser, $dbpass, $avatar);

# Create out db connection now that we have a real config file
$db = new DB($server, $dbuser, $dbpass, '');

# Create the new database and copy the template into it
setupDB($server, $dbuser, $dbpass, $database);

# Create an admin user
createAdmin($screenname, $username, $password);

return;

function writeConfig($server, $database, $dbuser, $dbpass, $avatar){
  # look for sample config file
  $ABSPATH=dirname(__FILE__).'/';
  if ( ! file_exists( $ABSPATH . 'config.php.sample' ) ){
    print "Couldn't find config.php.sample, aborting install";
    return;
  }
  
  # make sure avatars dir is writable
  if ( ! is_writable($ABSPATH) ){
    print "Couldn't write to $ABSPATH";
    return;
  }
  
  # create new config file
  $sample_config_file = file($ABSPATH . 'config.php.sample');
  $handle = fopen($ABSPATH . 'config.php', 'w');
  foreach ( $sample_config_file as &$line ) {
    $new_line = $line;
    if ( preg_match('/^\$db/', $line)){
      $new_line = preg_replace('/server/', $server, $new_line);
      $new_line = preg_replace('/username/', $dbuser, $new_line);
      $new_line = preg_replace('/password/', $dbpass, $new_line);
      $new_line = preg_replace('/database/', $database, $new_line);
    }
    if ( preg_match('/^\$avatardir/', $line)){
      $new_line = preg_replace('/avatars/', $avatar, $new_line);
    }
    fwrite($handle, $new_line);
  }
  fclose($handle);
  chmod($ABSPATH . 'config.php', 0664);
}

function setupDB($server, $dbuser, $dbpass, $database){
  global $db;

  $databases = $db->query(sprintf('SHOW DATABASES'));
  foreach($databases as $d){
    if( $d{'Database'} == $database)
      return print "The $database database already exists.\n";
  }

  $db->query(sprintf('CREATE DATABASE %s', $database));
  $db->query(sprintf('USE %s', $database));
  
  # dump the database file into the new databse
  # TODO Replace this with query(readline()) or something better
  system("mysql -u $dbuser -p$dbpass $database < ".$ABSPATH."database.sql");
  return;
}

function createAdmin($screenname, $username, $password){
  # create admin user
  $user = new User();
  $user->setScreenName($screenname);
  $user->setName($username);
  $user->setPassword($password);
  $user->setAdmin('1');
  $user->save();
  return;
}

function testDB($server, $dbuser, $dbpass) {
  # connect to database
  mysqli_connect($server, $dbuser, $dbpass, '');
  
  # bail if database connection failes
  if (mysqli_connect_error()) {
      die('Connect Error (' . mysqli_connect_errno() . ') '
              . mysqli_connect_error());
  }
  return;
}
