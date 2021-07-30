<?php
/*
The purpose of this script is populate the newly added "last_login" fields in the system_authentication and Members_subscriptions tables.
This is a "one-off" as the login script will update all three from now on.
*Note - this will not run within a web browser.
*/
die();  // Stop accidental execution.
// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Get the last_login data from the members table
$query = "
	SELECT member_id, last_login
	FROM members
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($member = $rsMembers->fetch(PDO::FETCH_ASSOC)){

	// Get the latest authentication record for this member
	$query = "
		SELECT uid
		FROM system_authentication
		WHERE member_id = :member_id
		ORDER BY timestamp DESC
		LIMIT 1
	";
	try {
		$rsUID = $mLink->prepare($query);
		$aValues = array(
			':member_id' => $member['member_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsUID->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$UID = $rsUID->fetch(PDO::FETCH_ASSOC);

	// Update the auth record
	$query = "
		UPDATE system_authentication
		SET last_login = :last_login
		WHERE member_id = :member_id
		AND uid = :uid
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':member_id' 	=> $member['member_id'],
			':last_login'	=> $member['last_login'],
			':uid'			=> $UID['uid']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// And then write last login to subscription record.
	$query = "
		UPDATE members_subscriptions
		SET last_login = :last_login
		WHERE member_id = :member_id
		AND active = 1
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $member['member_id'],
			':last_login'	=> $member['last_login']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

?>