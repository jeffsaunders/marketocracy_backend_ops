<?php
/*
The purpose of this script is get the CUSIP for stocks that are held whose CUSIP is missing in FrontBase.
*Note - this will not run within a web browser.
*/
die();  // Stop accidental execution.
// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Let's start outputting
$fp1 = fopen("/var/www/html/tmp/MissingCUSIP_".date("Ymd").".csv", "w");

// Write header row
$header = "Symbol,StockKey,CUSIP\r\n";
fwrite($fp1, $header);

// Get all the stocks that are missing a CUSIP (provided in a CSV loaded to a temp DB table - exclude header row)
	$query = "
		SELECT *
		FROM missing_cusip
		WHERE Symbol <> 'Symbol'
	";
try{
	$rsSymbols = $rLink->prepare($query);
	$rsSymbols->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them and count how many funds they have
while ($symbol = $rsSymbols->fetch(PDO::FETCH_ASSOC)){

	$query = "
		SELECT Cusip
		FROM cusip_feed
		WHERE Symbol = '".$symbol['Symbol']."'
	";
	try{
		$rsCUSIP = $fLink->prepare($query);
		//echo $query;//die();
		$rsCUSIP->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$CUSIP = $rsCUSIP->fetch(PDO::FETCH_ASSOC);

	// Write the row
	$row = '"'.$symbol['Symbol'].'","'.$symbol['StockKey'].'","'.$CUSIP['Cusip'].'"';
	$row .= "\r\n";
	fwrite($fp1, $row);
//die();
}

// Close 'er up
fclose($fp1);

echo "Done!\n";

?>