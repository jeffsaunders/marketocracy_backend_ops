<?php
// This script updates the rank_date values in the config table for each stie that displays them
// It needs to be run after the "Publish Rankings" step has completed - it tells the sites to look for the new data - Not sure why Brandon didn't do this as part of the "Publish" step???
// Have been updating these values manaully for 2 years - 'bout time I made it simple, eh?!
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 7/7/20

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
        parse_str($argv[1], $_REQUEST);
}

//Determine date to process
if (!isset($_REQUEST['date'])){
	echo "Missing Date - You must pass the date as follows: setRankingsDate.php \"date=20200630\"\nProcess Aborted!\n";
	die();
}
$sDate = date('Ymd', strtotime($_REQUEST['date']));

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctionsPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Let's get to it!
// Build an array of the links to the databases that need updating
// Listed in this order - mtr_marketaco, my_track_record, portfolio, sites_mds, sites_minc
$aLinks = array("tLink","mtrLink","mLink","mdsLink","sLink");

//print_r($aLinks);

// Step through the links array and update the tables
foreach($aLinks as $key=>$link){
	$query = "
		UPDATE site_config
		SET value = :rank_date
		WHERE setting = :setting
	";
	try{
		$rsUpdate = $$link->prepare($query);
		$aValues = array(
			':rank_date' => $sDate,
			':setting'   => "rank_date"
		);
		//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
//echo $error;
	}
}

echo "Ranking Date set to ".$sDate." successfully.\n";

?>
