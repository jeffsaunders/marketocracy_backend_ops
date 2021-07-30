<?php
// This commandline batch script checks for any stocks held in a member's fund that is no longer pricing on the market.
// By running it as a CRON you can set the deliver the report every day
// Example:
//	30 1 * * * /usr/bin/php /var/www/html/batch/getNonpricingPositions.php
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Load some useful functions
//require("../includes/systemFunctions.php");
require("/var/www/html/includes/systemFunctionsPDO.php");
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfigPDO.php");


/*
// Get all the stock symbols held in members' funds
$query = "
	SELECT DISTINCT(stockSymbol)
	FROM ".$fund_positions_table."
	ORDER BY stockSymbol ASC
";
try{
	$rs_symbols = $mLink->prepare($query);
	$rs_symbols->execute();
}
catch(PDOException $error){
// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$symbols = "";
while ($symbol = $rs_symbols->fetch(PDO::FETCH_ASSOC)){
	$symbols .= $symbol['stockSymbol'].",";
}

$symbols = substr($symbols, 0, -1);

*/

// Get all the stock symbols in the feed
$query = "
	SELECT Symbol
	FROM stock_feed
	ORDER BY Symbol ASC
";
try{
	$rs_symbols = $fLink->prepare($query);
	$rs_symbols->execute();
}
catch(PDOException $error){
// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$symbols = "";
while ($symbol = $rs_symbols->fetch(PDO::FETCH_ASSOC)){
	$symbols .= '"'.$symbol['Symbol'].'",';
}

// Pop the trailing comma off
$symbols = substr($symbols, 0, -1);

//echo $symbols;

// Get all the stock symbols held in members' funds NOT found in the feed ($symbols list)
$query = "
	SELECT DISTINCT(stockSymbol)
	FROM ".$fund_positions_table."
	WHERE stockSymbol NOT IN (".$symbols.")
	ORDER BY stockSymbol ASC
";
try{
	$rs_exceptions = $mLink->prepare($query);
	$rs_exceptions->execute();
}
catch(PDOException $error){
// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while ($exception = $rs_exceptions->fetch(PDO::FETCH_ASSOC)){
	echo $exception['stockSymbol']."\n";

// Need to write this to a CSV here and then email it!

}

?>
