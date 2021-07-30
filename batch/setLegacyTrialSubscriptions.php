<?php
/*
The purpose of this script is to create trial membership subscription records for all Legacy members upon paid platform launch.
You may want to manually truncate the members_subscriptions table - I don't do that here in case you want to modify this to add records later.

*Note - this will not run within a web browser.

*/

// OK, let's get going...
// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Start some counters
$GIPSCnt = 0;
$LegacyCnt = 0;

// First things first, grab all the members with GIPS Composites
$query = "
    SELECT m.member_id
	FROM members m, members_flags f
	WHERE m.member_id = f.member_id
	AND m.active = 1
	AND f.composite = 1
";
try{
	$rsGIPS = $mLink->prepare($query);
	$rsGIPS->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($member = $rsGIPS->fetch(PDO::FETCH_ASSOC)){

	// Create Free Pro subscriptions for them
	$query =
		"INSERT INTO members_subscriptions (
			member_id,
			active,
			product_id,
			start_timestamp,
			bill_frequency,
			next_bill_timestamp
		) VALUES (
			".$member['member_id'].",
			1,
			10,
			UNIX_TIMESTAMP(),
			'Never',
			NULL
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$GIPSCnt++;

}

// Now grab everyone else
$query = "
    SELECT m.member_id
	FROM members m, members_flags f
	WHERE m.member_id = f.member_id
	AND m.active = 1
	AND f.composite = 0
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($member = $rsMembers->fetch(PDO::FETCH_ASSOC)){

	// Create Trial subscriptions for them
	$query =
		"INSERT INTO members_subscriptions (
			member_id,
			active,
			product_id,
			start_timestamp,
			bill_frequency,
			next_bill_timestamp
		) VALUES (
			".$member['member_id'].",
			1,
			99,
			UNIX_TIMESTAMP(),
			'Never',
			NULL
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$LegacyCnt++;

}

echo $GIPSCnt." GIPS Composite Members Added.\n";
echo $LegacyCnt." Legacy Members Added.\n";
echo $GIPSCnt+$LegacyCnt." Total Members Added.\n\n";

?>
