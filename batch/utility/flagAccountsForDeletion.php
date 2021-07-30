<?php
/*
The purpose of this script is to flag all rows in clear_passwords that are destined for deletion from FrontBase
This must be run on one of the new servers as the version of the MCRYPT library on the Fetch server is too old to support our encryption settings.
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

// Grab the manager keys flagged for deletion
$query =
	"SELECT fb_primarykey
	 FROM list_email_validate
	 WHERE action = 'delete'
";
try{
	$rsDelete = $mLink->prepare($query);
	$rsDelete->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them and flag all the matches in clear_passwords

$flags = 0;

while ($member = $rsDelete->fetch(PDO::FETCH_ASSOC)){

//$managerKey = addslashes(strtoupper('%88b21c1140511491c0a80133%'));
//echo $managerKey."\n";
	$managerKey = "%".strtoupper(substr($member['fb_primarykey'], 2, 24))."%";

	$query = "
		UPDATE clear_passwords
		SET flagged_for_deletion = 1
		WHERE UCASE(manager_key) LIKE :manager_key
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':manager_key' => $managerKey
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$flagged = $rsUpdate->rowCount();
	$flags = $flags + $flagged;
//die($error);
}

echo $flags." Accounts Flagged.";
?>