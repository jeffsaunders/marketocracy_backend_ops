<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any fundPrice API calls submitted through the legacyData daemons
*/

// Tell me when things go sideways
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Define which API server instance we are running (folder, name)
$aAPI = array('api','API');
//$aAPI = array('api2','API2');

// Define process and use to define directory for results
$process = "fundprice";

// OK, let's get going...

// Pull in the global stuff
require("includes/resultsGlobal.php");

// Do this forever
while (true){

	// Load up the results processing loop
	require_once("includes/resultsLoop.php");

	// If we got here then no more files, bail.
	break;

} // End
?>