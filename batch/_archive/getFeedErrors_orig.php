<?php
// This commandline batch script generates a report of any stock price feed exceptions (`outcome` value other than "Success").
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
	SELECT *
	FROM stock_feed
	WHERE Outcome NOT LIKE 'Success'
	ORDER BY Outcome, Symbol
";
//echo $query;die();
$rs_exceptions = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Let's start outputting
$fp = fopen("/var/www/html/tmp/StockFeedExceptions_".date("Ymd").".csv", "w");

// Write header row
$headers = "Exception,Symbol,Message,Name,Market,MarketIDCode,Category\r\n";
fwrite($fp, $headers);

// Loop through all the results
while($exceptions = mysql_fetch_array($rs_exceptions)){

	// Assign the values to variables
	$exception 	= trim($exceptions['Outcome']);
	$symbol		= trim($exceptions['Symbol']);
	$message  	= trim($exceptions['Message']);
	$name		= trim($exceptions['Name']);
	$market		= trim($exceptions['Market']);
	$marketID 	= trim($exceptions['MarketIdentificationCode']);
	$category 	= trim($exceptions['CategoryOrIndustry']);

	// Write the row
	$row = '"'.$exception.'","'.$symbol.'","'.$message.'","'.$name.'","'.$market.'","'.$marketID.'","'.$category.'"';
	$row .= "\r\n";
	fwrite($fp, $row);
}

// Close 'er up
fclose($fp);

$emailBody = "The attached report is a list of stock symbols in today's price feed file that are not pricing, along with the reason (where available).";

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Stock Feed Exceptions Report';
$email->Body      = $emailBody;
$email->AddAddress('ops-actions@marketocracy.com');
//$email->AddAddress('jeff.saunders@marketocracy.com');
$email->AddAttachment('/var/www/html/tmp/StockFeedExceptions_'.date("Ymd").'.csv');
$email->Send();

// Delete report
unlink('/var/www/html/tmp/StockFeedExceptions_'.date("Ymd").'.csv');

?>
