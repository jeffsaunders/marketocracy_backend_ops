<?php
// This script calculates the starting fund pricing data for any manager who's switched to FOLIOfn.
// It requires that a folio_cutover date be defined in the members_fund table before execution.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 9/19/18
// Modified by: Jeff Saunders - 9/21/18 - Added check to confirm the cutover date is defined and stop execution if it is not.
// Modified by: Jeff Saunders - 10/17/18 - Switched data source from the members_fund_positions table to members_fund_pricing as not all positions are there on the cutover dates and we can't pull fresh data because the stock price file is truncated.
// Modified by: Jeff Saunders - 10/18/18 - Converted for cammandline execution.

//die("accidental execution blocked - comment out line 10 to run");

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();
$pdo_log = "/var/log/httpd/marketaco-pdo_log";

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

// Make sure a properly formatted fund ID is passed
if (!isset($_REQUEST['fund']) || !strrchr($_REQUEST['fund'], "-")){

	echo 'You Must Pass A Fund ID (e.g. "fund=1-1") - ABORTED.';
	die();

}
$fund_id = $_REQUEST['fund'];
$member_id = explode("-", $_REQUEST['fund'])[0];

// Make sure a properly formatted total value is passed
if (!isset($_REQUEST['value']) || !is_numeric($_REQUEST['value'])){

	echo 'You Must Pass A Fund Total Value (e.g. "value=1223456.78") - ABORTED.';
	die();

}
$folioFundValue = $_REQUEST['value'];

// Get the folio cutover date from the funds table
$query = "
	SELECT folio_cutover
	FROM members_fund
	WHERE fund_id=:fund_id
";
try{
	$rsCutover = $tLink->prepare($query);
	$aValues = array(
		':fund_id'	=> $fund_id
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;//die();
	$rsCutover->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$folioCutover = $rsCutover->fetchColumn();

if ($folioCutover == ""){

	echo 'Fund '.$fund_id.' is missing it\'s cutover date - Please add it to the members_fund table - ABORTED.';
	die();

}

// Assign the date to query for (The day before the cutover)
$lastDay = date('Ymd', strtotime("-1 day", $folioCutover));

// Get the pricing data from Portfolio on the last day before cutover
$query = "
	SELECT *
	FROM members_fund_pricing
	WHERE fund_id = :fund_id
	AND date = :date
";
try{
	$rsPrice = $mLink->prepare($query);
	$aValues = array(
		':fund_id'	=> $fund_id,
		':date'		=> $lastDay
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;//die();
	$rsPrice->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$finalPrice = $rsPrice->fetch(PDO::FETCH_ASSOC);

// Calculate some more values
$portTotalValue = $finalPrice['totalValue'];
//$portNAV = round(($portTotalValue / 100000), 2);
$portNAV = round(($finalPrice['price']), 2);
$folioShares = round(($folioFundValue / $portNAV), 12);

// Insert start record into folio_fund_pricing
$query = "
	INSERT INTO folio_fund_pricing (
		member_id,
		fund_id,
		start_record,
		timestamp,
		date,
		unix_date,
		startCash,
		positionsValue,
		cashValue,
		totalValue,
		price,
		shares
	)VALUES(
		:member_id,
		:fund_id,
		:start_record,
		UNIX_TIMESTAMP(),
		:date,
		:unix_date,
		:startCash,
		:positionsValue,
		:cashValue,
		:totalValue,
		:price,
		:shares
	)

";
try{
	$rsPortfolioPrice = $tLink->prepare($query);
	$aValues = array(
		':member_id'		=> $member_id,
		':fund_id'			=> $fund_id,
		':start_record'		=> 1,
		':date'				=> date('Ymd', $folioCutover),
		':unix_date'		=> $folioCutover,
		':startCash'		=> NULL,
		':positionsValue'	=> NULL,
		':cashValue'		=> NULL,
		':totalValue'		=> $folioFundValue,
		':price'			=> $portNAV,
		':shares'			=> $folioShares
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery."\n\n";
	$rsPortfolioPrice->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

echo "Done.";

?>
