<?php
/*
The purpose of this script is to populate the authentication table with encrypted values for a member imported from the old system
This must be run on one of the new servers as the version of the MCRYPT library on the Fetch server is too old to support our encryption settings,
thus it must be run separately from, and after, the import process done on the fetch server.
*/

// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Assign and encrypt passed values
$username = trim(strtolower($_REQUEST['username']));
$encrypted_username = encrypt($username);
$password = trim($_REQUEST['password']);
$encrypted_password = encrypt($password);
if ($_REQUEST['email'] == ""){
	$email = NULL;
	$encrypted_email = NULL;
}else{
	$email = trim(strtolower($_REQUEST['email']));
	$encrypted_email = encrypt($email);
}
$member_id = $_REQUEST['member_id'];

//echo $member_id."|".$username."|".$encrypted_username."|".$password."|".$encrypted_password."|".$email."|".$encrypted_email."\n";

// Insert the encrypted values into the auth table
$query =
	"INSERT INTO system_authentication (
			member_id,
			timestamp,
			username,
			password,
			email,
			email_validated_timestamp,
			imported
		) VALUES (
			:member_id,
			UNIX_TIMESTAMP(),
			:username,
			:password,
			:email,
			UNIX_TIMESTAMP(),
			1
		)";
$rsInsert = $mLink->prepare($query);
$aValues = array(
	':member_id'	=> $member_id,
	':username'		=> $encrypted_username,
	':password'		=> $encrypted_password,
	':email'		=> $encrypted_email
);
$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
$rsInsert->execute($aValues);

?>