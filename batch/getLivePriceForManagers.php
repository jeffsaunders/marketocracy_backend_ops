<?php
// This script runs a livePrice for every GIPS Composite holder.  It is run periodically through CRON.
// The result is to update the current value of their fund (and the comparable value of the tracking indexes) so that the values displayed on MTR are as current as possible, regardless whether they are logged into Portfolio.
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

// Get the fund information for the livePrice queries
// Grabs all the Composite fund IDs as of the most recent ranking period
$query = "
	SELECT fund_id, username, fund_symbol
	FROM rank_report_pro
	WHERE composite = 'yes'
	AND as_of_date = (SELECT MAX(as_of_date) FROM rank_report_pro)
";
try {
	$rsFunds = $mLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Set the starting port number for the API calls
$port = rand(52100, 52499);

while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

//print_r($fund);

	// Build livePrice API query string
	$query = "livePrice|0|".$fund['username']."|".$fund['fund_id']."|".$fund['fund_symbol'];

//livePrice|0|crees|402-1|10stx
//echo $query."\n\n";

	// Set the port number for the API call (API2)
	if ($port == 52499){ // Last port #, roll over
		$port = 52100;
	}else{
		$port++;
	}

	// Execute an EXPECT script to call the API
	$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	exec($cmd);

}

// Done!

?>