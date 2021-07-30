<?php
/*
The purpose of this script is to build a file of login credentials for all Basic members who have paid upgrades for importation into Moodle
This must be run on one of the new servers as the version of the MCRYPT library on the Fetch server is too old to support our encryption settings.
*/
//die("Accidental Execution Stopped");  // Stop accidental execution.
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

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Let's start outputting
$fp1 = fopen("/var/www/html/tmp/PayingBasicMembersCredentialsForMoodle.csv", "w");

// Write header row
$header = "firstname,lastname,username,email,password\r\n";
fwrite($fp1, $header);

// Get the credentials for all paying Basic members
$query = "
	SELECT member_id, name_first, name_last, username, email
	FROM members
	WHERE member_id IN (SELECT member_id
						FROM members_subscriptions
						WHERE active = 1
						AND product_id = 5)
	";
try{
	$rsManagers = $mLink->prepare($query);
	$rsManagers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while ($manager = $rsManagers->fetch(PDO::FETCH_ASSOC)){

	// Assign values
	$member_id	= $manager['member_id'];
	$firstname	= $manager['name_first'];
	$lastname	= $manager['name_last'];
	$username	= $manager['username'];
	$email		= $manager['email'];

	// Get their password
	$query = "
		SELECT password
		FROM system_authentication
		WHERE member_id = ".$member_id."
		ORDER BY timestamp DESC LIMIT 1
	";
	try{
		$rsPassword = $mLink->prepare($query);
		$rsPassword->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$pass = $rsPassword->fetch(PDO::FETCH_ASSOC);

	// Decrypt it
	$encrypted_password = $pass['password'];
	$password = trim(decrypt($encrypted_password));

	// Write the row
	$row = '"'.$firstname.'","'.$lastname.'","'.$username.'","'.$email.'","'.$password.'"';
	$row .= "\r\n";
	fwrite($fp1, $row);

}

// Close 'er up
fclose($fp1);

echo "Done!\n";

?>