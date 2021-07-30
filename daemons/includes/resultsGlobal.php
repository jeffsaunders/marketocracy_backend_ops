<?php
/*
This include covers the functions and global var declarations for all the API results daemons.
Called from daemons/<calling daemon>
*/

// A function that searches recursive arrays
function recursive_array_search($needle, $haystack){
	foreach($haystack as $key=>$value){
		$current_key = $key;
		if ($needle === $value OR (is_array($value) && recursive_array_search($needle, $value) !== false)){
			return $current_key;
		}
	}
	return false;
}

// Run forever
set_time_limit(0);

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define directory to process (vars assigned in parent)
$directory = "/".$aAPI[0]."/".$process."_processing/";

if (isset($overflow) && $overflow == true){
	$directory = "/".$aAPI[0]."/".$process."_processing/tmp/";
}

// TEMP!!!!!!
//if ($aAPI[0] == "api3"){
//	$directory = "/".$aAPI[0]."/".$process."_processing/output/";
//}
?>