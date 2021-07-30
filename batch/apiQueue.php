<?php
/*
The purpose of this script is to populate the api_queue table in the reports db with the current number of each type of file in each API queue directory.
This must be run from the command line and is cron driven.
*/

// OK, let's get going...

// Define some global system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Declare some arrays
$aAPIs = array("API1","API2","API3");
$aDirectories = array("/api/","/api2/","/api3/");
$aProcesses = array("fundprice_processing","stockprice_processing","ecn_processing","manageradmin_processing","ranking_processing","trade_processing","ca_processing");
$aTypes = array("input","tmp","output","processing");
$aStrings = array();

// What time is it?
$start = time();

// Loop through the directories array
for ($d = 0; $d < count($aDirectories); $d++){
	// Loop through the processes array
	for ($p = 0; $p < count($aProcesses); $p++){
		// (re)initialize the pipe-delimited values string
		$string = "";
		// Loop through the types array
		for ($t = 0; $t < count($aTypes); $t++){
			// Build command string - returns the number of files matching the search criteria
			$cmd = "find ".$aDirectories[$d].$aProcesses[$p]." -maxdepth 1 -name \"*".$aTypes[$t]."*\" -type f | wc -l";
			// Execute the command an assign the returned number to a var
			$count = exec($cmd);
			// Concatonate the number onto the pipe-delimited string
			$string .= $count."|";
		}
		// Pop the trailing "|" off the string
		$string = substr($string, 0, -1);
		// Push the string onto the strings array
		$aStrings[$p] = $string;
	}

	// Update the strings stored in the DB
	$query = "UPDATE ".$api_queue_table." SET ";
	// Loop through the processes and add the SET values
	for ($p = 0; $p < count($aProcesses); $p++){
		$query .= $aProcesses[$p]." = '".$aStrings[$p]."', ";
	}
	$query .= "timestamp = UNIX_TIMESTAMP()";
	$query .= " WHERE api = '".$aAPIs[$d]."'";
	//echo $query."\n\n";

	// Do it
	$rsUpdate = $mLink->prepare($query);
	$rsUpdate->execute();

	// If we've hit the end of the directories, wait a sec then start all over again
	if ($d == (count($aDirectories)-1)){
		sleep(1);
		$d = -1;
	}

	// Die every 6 hours to clear out the cobwebs.  Will auto-restart.
	if (time() - $start > 21600){ // 6 hours
		break;
	}
}

?>
