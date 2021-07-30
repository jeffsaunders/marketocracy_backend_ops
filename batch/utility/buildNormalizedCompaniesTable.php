<?php
/*
The purpose of this script is to apply corporate actions to a table of companies that existed at the inception of Portfolio, resulting in a normalized company list to work from on forward.
*Note - this will not run within a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run long enough
set_time_limit(900); // 15 minutes

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");





Grab all CAs of each type, in succession (in order below), and apply them, in succession, per day, starting 5/1/00


delistingsOnDate







bankruptciesOnDate
symbolChangesOnDate
listingsOnDate
listingChangesOnDate
nameChangesOnDate
cusipChangesOnDate
	cashDividendsOnDate
	stockDividendsOnDate
acquisitionsOnDate
	splitsOnDate
spinoffsOnDate



?>
