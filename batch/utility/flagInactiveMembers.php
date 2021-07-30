<?php
/*
The purpose of this script is insert an inactive_timestamp value into the subscription records of all members who have not yet started their trial.
This is a "one-off" but could be modified for other bulk flagging in the future.
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

// Get the subscription records without a start_timestamp
$query = "
	SELECT uid
	FROM members_subscriptions
	WHERE start_timestamp IS NULL
";
try{
	$rsSubs = $mLink->prepare($query);
	$rsSubs->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($sub = $rsSubs->fetch(PDO::FETCH_ASSOC)){

	// Update their inactive_timestamp.
	$query = "
		UPDATE members_subscriptions
		SET inactive_timestamp = UNIX_TIMESTAMP()
		WHERE uid = :uid
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':uid'	=> $sub['uid']
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