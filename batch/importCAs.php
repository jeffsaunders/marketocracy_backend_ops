<?php
/*
This process imports corporate actions for the given date range.
By default it uses the same start and end dates, to get "yesterday's" CAs.
*/

// Define some global system settings
date_default_timezone_set('America/New_York');
////error_reporting(E_ALL);  // Show ALL, including warnings and notices
//error_reporting(E_ERROR);  // Just show hard errors
//ini_set('display_errors', '1');  // Show 'em

// Declare all the methods (in processing order)
$aMethods = array(
			"delistingsOnDate",
			"bankruptciesOnDate",
			"symbolChangesOnDate",
			"listingsOnDate",
			"listingChangesOnDate",
			"nameChangesOnDate",
			"cusipChangesOnDate",
			"cashDividendsOnDate",
			"stockDividendsOnDate",
			"acquisitionsOnDate",
			"splitsOnDate",
			"spinoffsOnDate"
			);

// Set the start and end dates
// If it's Tuesday morning, run for Sat, Sun, and Mon.
if (date("w") == 2){
	$startDate = date("Y-m-d", strtotime("-3 days"));
	$endDate = date("Y-m-d", strtotime("-1 day"));
}else{
	$startDate = date("Y-m-d", strtotime("-1 day"));
	$endDate = $startDate;
}

// Manual override (uncomment)
//$startDate = "2000-01-01";
//$endDate = "2016-05-08";

$date = strtotime($startDate);

// Set the API port(s)
$start_port = rand(52100, 52499);
$stop_port = 52499;

// Initialize current port
$port = $start_port;

while ($date <= strtotime($endDate)){
//	echo date("m/d/Y", $date)."\n";

	for ($x = 0; $x < sizeof($aMethods); $x++){

		// Build the API query
		$query = $aMethods[$x]."|0|".date("Ymd", $date);
//echo $query."\n";
		// Set the port number for the API call
		if ($port >= $stop_port){
			$port = $start_port;
			sleep(1);
		}else{
			$port++;
		}
//echo $port."\n";

		// Execute the query call to submit the request
		exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

	}

	// Incerment the date
	$date = strtotime("+1 day", $date);
}

?>