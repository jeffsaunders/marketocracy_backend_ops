<?php
// This script adds the fisrt and last name of the tracked managers to a temporary list of funds tracked by fund_id.
// It's a "one-off" but could be adapted as needed.
// *Note - this will not run within a web browser.

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Get all the tracker emails records stored in the temporary table
$query = "
	SELECT *
	FROM tracker_emails_from_portfolio
";
try {
	$rsTrackers = $mLink->prepare($query);
	$rsTrackers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($tracker = $rsTrackers->fetch(PDO::FETCH_ASSOC)){

	// Array for names
	$aManagerNames = array();

	// Pull the fund_ids being tracked
	$aFunds = explode("|", $tracker["track_funds"]);

 	for ($cnt = 0; $cnt < sizeof($aFunds); $cnt++){

		// Pull the manager's member_id from the fund_id
		$fund_id = $aFunds[$cnt];
		$aElements = explode("-", $fund_id);
		$manager_id = $aElements[0];

		// look up the manager's name
		$query = "
			SELECT name_first, name_last
			FROM members
			WHERE member_id = ".$manager_id."
		";
		try {
			$rsManager = $mLink->prepare($query);
			$rsManager->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$manager = $rsManager->fetch(PDO::FETCH_ASSOC);
		$manager_name = $manager["name_first"]." ".$manager["name_last"];

		// add the name to array
		array_push($aManagerNames, $manager_name);
	}

	// dedupe the array
	$aManagers = array_unique($aManagerNames);

	// build a string of the manager names left in the array
	$managers_list = "";
	for ($cntr = 0; $cntr < sizeof($aManagers); $cntr++){
		$managers_list .= $aManagers[$cntr].", ";
	}
	// Pop the trailing ", " off
	$managers_list = substr($managers_list, 0, -2);

	// save the names, comma delimited to the track column
	$query = "
		UPDATE tracker_emails_from_portfolio
		SET track = '".$managers_list."'
		WHERE uid = ".$tracker["uid"]."
	";
	try {
		$rsUpdate = $mLink->prepare($query);
		$rsUpdate->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}
?>
