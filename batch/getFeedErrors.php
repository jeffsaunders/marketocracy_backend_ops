<?php
// This commandline batch script generates a report of any stock price feed exceptions (`outcome` value other than "Success").
// It also now flags those whose closing price was below a penny.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Check for not "Success"
$query = "
	SELECT *
	FROM stock_feed
	WHERE Outcome NOT LIKE 'Success'
	OR (Outcome LIKE 'Success' AND PreviousClose < 0.01)
	ORDER BY Outcome, Symbol
";
//echo $query;die();
try{
	$rsExceptions = $fLink->prepare($query);
	$rsExceptions->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	$aErrors[] = $error;
}

// Let's start outputting
$fp = fopen("/var/www/html/tmp/StockFeedExceptions_".date("Ymd").".csv", "w");

// Write header row
$headers = "Exception,Symbol,Message,Name,Market,MarketIDCode,Category,Active Members Hold,From FrontBase,From Xignite,On Static List,Previous Close\r\n";
fwrite($fp, $headers);

// Let's get loopin'
$emptyReport = true;

// Loop through all the results
while($exceptions = $rsExceptions->fetch(PDO::FETCH_ASSOC)){

	// Assign the values to variables
	$exception 	= trim($exceptions['Outcome']);
	$symbol		= trim($exceptions['Symbol']);
	$message  	= trim($exceptions['Message']);
	$name		= trim($exceptions['Name']);
	$market		= trim($exceptions['Market']);
	$marketID 	= trim($exceptions['MarketIdentificationCode']);
	$category 	= trim($exceptions['CategoryOrIndustry']);
	$closeZero	= ($exceptions['PreviousClose'] < "0.01" ? $exceptions['PreviousClose'] : "");

	// Initialize some others
	$xignite	= "";
	$frontbase	= "";
	$static		= "";

    // See if this stock symbol came from Xignite first.
	$query = "
		SELECT COUNT(*) AS count
		FROM symbols_xignite
		WHERE symbol = :symbol
	";
	try {
		$rsCount = $fLink->prepare($query);
		$aValues = array(
			':symbol'	=> $symbol
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsCount->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$cnt = $rsCount->fetch(PDO::FETCH_ASSOC);
	$count = $cnt['count'];
	if ($count > 0){

		// Skip those that are not successful but were supplied by Xignite's morning symbol feed - we can't fix those anyway.
		if ($exception != "Success"){
			continue;
		}
		$xignite = "YES";
	}

	// Ok, we can do something about the rest so let's gather more info...
    // Count how many members hold this stock
	$query = "
		SELECT COUNT(DISTINCT mf.member_id) AS count
		FROM members_fund mf, members_fund_stratification_basic mfsb
		WHERE mf.active = 1
		AND mfsb.stockSymbol = :symbol
		AND mfsb.totalShares > 0
		AND mf.fund_id = mfsb.fund_id
	";
	try {
		$rsCount = $mLink->prepare($query);
		$aValues = array(
			':symbol'	=> $symbol
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsCount->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$cnt = $rsCount->fetch(PDO::FETCH_ASSOC);
	$holders = $cnt['count'];

	// Skip those no one holds
	if ($holders == 0){
		continue;
	}

    // See if this stock symbol came from FrontBase.
	$query = "
		SELECT COUNT(*) AS count
		FROM symbols_frontbase
		WHERE symbol = :symbol
	";
	try {
		$rsCount = $fLink->prepare($query);
		$aValues = array(
			':symbol'	=> $symbol
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsCount->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$cnt = $rsCount->fetch(PDO::FETCH_ASSOC);
	$count = $cnt['count'];
	if ($count > 0){
		$frontbase = "YES";
	}

    // Finally, see if this stock is on the STATIC list (probably not)
	$query = "
		SELECT COUNT(*) AS count
		FROM symbols_static
		WHERE symbol = :symbol
	";
	try {
		$rsCount = $fLink->prepare($query);
		$aValues = array(
			':symbol'	=> $symbol
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsCount->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$cnt = $rsCount->fetch(PDO::FETCH_ASSOC);
	$count = $cnt['count'];
	if ($count > 0){
		$static = "YES";
	}

	// Write the row
	$row = '"'.$exception.'","'.$symbol.'","'.$message.'","'.$name.'","'.$market.'","'.$marketID.'","'.$category.'","'.$holders.'","'.$frontbase.'","'.$xignite.'","'.$static.'","'.$closeZero.'"';
	$row .= "\r\n";
	fwrite($fp, $row);

	$emptyReport = false;
}

// Close 'er up
fclose($fp);

$emailBody = "The attached report is a list of stock symbols in today's price feed file that are not pricing or are pricing below $.01.";

if ($emptyReport){
	$emailBody = "No actionable exceptions found today.";
}

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Stock Feed Exceptions Report';
$email->Body      = $emailBody;
//$email->AddAddress('ops-actions@marketocracy.com');
$email->AddAddress('marty.fukui@marketocracy.com');
$email->AddBCC('jeff.saunders@marketocracy.com');
if (!$emptyReport){
	$email->AddAttachment('/var/www/html/tmp/StockFeedExceptions_'.date("Ymd").'.csv');
}
$email->Send();

// Delete report
unlink('/var/www/html/tmp/StockFeedExceptions_'.date("Ymd").'.csv');

?>
