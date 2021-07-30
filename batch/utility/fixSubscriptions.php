<?php
/*
The purpose of this script is to restore all members trial subscription records who did not participate in the trial campaign so they can start their trial upon first login.
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

//Get all the old trial subscription records (99)
$query = "
	SELECT *
	FROM members_subscriptions
	WHERE product_id = 99
	AND start_timestamp IS NULL
	AND active = 0
";
try{
	$rsBadSubs = $mLink->prepare($query);
	$rsBadSubs->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them and look for the accompanying non-legacy trial record (0) and delete it
while ($badSub = $rsBadSubs->fetch(PDO::FETCH_ASSOC)){

	$query = "
		DELETE FROM members_subscriptions
		WHERE product_id = 0
		AND member_id = ".$badSub['member_id']."
	";
	try{
		$rsDelete = $mLink->prepare($query);
		//echo $query;//die();
		$rsDelete->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Reset the active flag on the old trial record
	$query = "
		UPDATE members_subscriptions
		SET active = 1
		WHERE uid = ".$badSub['uid']."
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		//echo $query;//die();
		$rsUpdate->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	echo ".";
//die();
}

echo "Done!\n";

?>