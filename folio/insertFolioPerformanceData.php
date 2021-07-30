<?php
// This script calculates and inserts data from the FOLIOfn Model Performance Chart into the folio_fund_pricing table.
// This requires the passing of three values - fund_id, date, and market_value on that date.  A version with a web form will be developed once proof of concept is achieved.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 3/11/18
// Modified by: Jeff Saunders - 3/15/18 - Added verification that there is at least one pricing record (start record created by calc-start process) for the fund in question

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Set process date strings
if (isset($_REQUEST['date']) && date('Y-m-d', strtotime($_REQUEST['date']) != "1969-12-31")){
	$processDate = date('Ymd', strtotime($_REQUEST['date']));
	$unixDate = strtotime($_REQUEST['date']);
}else{
	echo 'You Must Pass A Valid Record Date (e.g. "date=20181015" or "date=10/15/18") - ABORTED.';
	die("\n\n");
}

//echo $processDate."\n";echo $unixDate."\n";die();

// Set fund ID
if (isset($_REQUEST['fund'])){
	$afund = explode('-', $_REQUEST['fund']);
	if (count($afund) == 2){
		if (is_numeric($afund[0]) && is_numeric($afund[1])){
			$fundID = $_REQUEST['fund'];
			$memberID = explode("-", $fundID)[0];
		}else{
			echo 'You Must Pass A Properly Formatted Fund ID (e.g. "fund=1-1") - ABORTED.';
			die("\n\n");
		}
	}else{
		echo 'You Must Pass A Properly Formatted Fund ID (e.g. "fund=1-1") - ABORTED.';
		die("\n\n");
	}
}else{
	echo 'You Must Pass A Properly Formatted Fund ID (e.g. "fund=1-1") - ABORTED.';
	die("\n\n");
}

// Make sure the fundID is valid
$query = "
	SELECT count(fund_id)
	FROM folio_fund_pricing
	WHERE fund_id = :fund_id
";
try{
	$rsCount = $tLink->prepare($query);
	$aValues = array(
		':fund_id'	=> $fundID
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery."\n";//die();
	$rsCount->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$records = $rsCount->fetchColumn();

if ($records < 1){
	echo 'The Fund ID You Passed Has No Pricing Records, Run calc-start Process For Fund '.$fundID.' - ABORTED.';
	die("\n\n");
}

//echo $fundID."\n";echo $records."\n";die();

// Set total value
if (isset($_REQUEST['value']) && is_numeric($_REQUEST['value'])){
	$totalValue = $_REQUEST['value'];
}else{
	echo 'You Must Pass A Numeric Total Value (e.g. "value=299704.34964") - ABORTED.';
	die("\n\n");
}

// Get the shares quantity for price calculation
$query = "
	SELECT shares
	FROM folio_fund_pricing
	WHERE fund_id = :fund_id
	ORDER BY unix_date DESC
	LIMIT 1
";
try{
	$rsShares = $tLink->prepare($query);
	$aValues = array(
		':fund_id'	=> $fundID
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery."\n";//die();
	$rsShares->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$shares = $rsShares->fetchColumn();

// Calculate the price (NAV) value
$price = $totalValue / $shares;

//echo $shares."\n";echo $price."\n";die();

// Insert the row
$query = "
	INSERT INTO folio_fund_pricing (
		member_id,
		fund_id,
		timestamp,
		date,
		unix_date,
		totalValue,
		price,
		shares
	) VALUES (
		:member_id,
		:fund_id,
		UNIX_TIMESTAMP(),
		:date,
		:unix_date,
		:value,
		:price,
		:shares
	)
";
try{
	$rsInsert = $tLink->prepare($query);
	$aValues = array(
		':member_id'=> $memberID,
		':fund_id' 	=> $fundID,
		':date' 	=> $processDate,
		':unix_date'=> $unixDate,
		':value'	=> $totalValue,
		':price' 	=> $price,
		':shares' 	=> $shares
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;die();
	$rsInsert->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	$aErrors[] = $error;
}

echo "Done!\n";

?>
