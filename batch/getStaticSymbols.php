<?php
// This commandline batch script generates a report of any stock symbol found on the static list AND either the FrontBase or Xignite lists (For removal).
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Check for not "Success"
$query = "
	SELECT symbol
	FROM symbols_static
	ORDER BY symbol ASC
";
//echo $query;die();
$rs_statics = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Let's start outputting
$fp = fopen("/var/www/html/tmp/StaticSymbolsCheck_".date("Ymd").".csv", "w");

// Write header row
$headers = "Symbol,On Xignite List,On FrontBase List\r\n";
fwrite($fp, $headers);

// Loop through all the results
while($statics = mysql_fetch_array($rs_statics)){
	$static = $statics['symbol'];
	$xignite = "";
	$frontBase = "";

	// Is it on the Xignite list?
	$query = "
		SELECT symbol
		FROM symbols_xignite
		WHERE symbol = '".$static."'
	";

	//echo $query;die();
	$rs_xignite = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

	if (mysql_num_rows($rs_xignite) > 0){
		$xignite = "YES";
	}

	// Is it on the FrontBase list?
	$query = "
		SELECT symbol
		FROM symbols_frontbase
		WHERE symbol = '".$static."'
	";

	//echo $query;die();
	$rs_frontbase = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

	if (mysql_num_rows($rs_frontbase) > 0){
		$frontBase = "YES";
	}

 	// Write the row
	$row = '"'.$static.'","'.$xignite.'","'.$frontBase.'"';
	$row .= "\r\n";
	fwrite($fp, $row);
}

// Close 'er up
fclose($fp);

$emailBody = "The attached report is a list of stock symbols in the STATIC table with indication of whether they also exist on the primary feed tables (for removal purposes).";

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Stock Feed Exceptions Report';
$email->Body      = $emailBody;
$email->AddAddress('ops-actions@marketocracy.com');
//$email->AddAddress('jeff.saunders@marketocracy.com');
$email->AddAttachment('/var/www/html/tmp/StaticSymbolsCheck_'.date("Ymd").'.csv');
$email->Send();

// Delete report
unlink('/var/www/html/tmp/StaticSymbolsCheck_'.date("Ymd").'.csv');

?>
