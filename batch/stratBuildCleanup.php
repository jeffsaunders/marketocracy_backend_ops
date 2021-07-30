<?php
// This script clears out any hanging stratification build flags that were left set (processing = 1) in the members_fund table.
// When that flag is set the member sees a perpetual "Stratification is currently being updated" message and no rebuild will execute
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

// Update all funds with processing set to TRUE
$query = "
	UPDATE ".$fund_table."
	SET processing	= 0
	WHERE processing = 1
";
try {
	$rsUpdate = $mLink->prepare($query);
	$rsUpdate->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// That's it - pretty simple

?>