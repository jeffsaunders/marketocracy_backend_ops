<?php
/*
The purpose of this script is to populate the stocks_companies and stocks_symbols tables with data from the Xignite feed.  Also assigns sectors.
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

// Get all the stocks that are in the Xignite feed table, including those not pricing (the more, the merrier)
	$query = "
		SELECT s.*, c.Cusip
		FROM stock_feed s
		JOIN cusip_feed c ON c.Symbol = s.Symbol
	";
try{
	$rsStocks = $fLink->prepare($query);
	$rsStocks->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($stock = $rsStocks->fetch(PDO::FETCH_ASSOC)){

	// Get the stock's sector_code
	$query = "
		SELECT sector_id, sub_sector_id
		FROM stocks_sub_sectors
		WHERE sub_sector_name = '".trim($stock['CategoryOrIndustry'])."'
	";
	try{
		$rsSector = $mLink->prepare($query);
		//echo $query;//die();
		$rsSector->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$sector = $rsSector->fetch(PDO::FETCH_ASSOC);

	// Assign code
	$sector_code = $sector['sector_id']."-".$sector['sub_sector_id'];

	// If it's not found, thus "null-null" then assign it the "Unknown" sector code
	if ($sector_code == "-"){
		$sector_code = "05-135";
	}

	// Create the company record
	$query = "
		INSERT INTO stocks_companies (
			company_name,
			active,
			effective_timestamp,
			created
		) VALUES (
			:company_name,
			1,
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP()
		)
	";
	try {
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':company_name'	=> $stock['Name']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsInsert->execute($aValues);

		// Store off the just assigned company id.
		$company_id = $mLink->lastInsertId();
	}
	catch(PDOException $error){
		// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Create the new stock symbol record
	$query = "
		INSERT INTO stocks_symbols (
			symbol,
			company_id,
			active,
			effective_timestamp,
			exchange,
			sector_code,
			cusip,
			created
		) VALUES (
			:symbol,
			:company_id,
			1,
			UNIX_TIMESTAMP(),
			:exchange,
			:sector_code,
			:cusip,
			UNIX_TIMESTAMP()
		)
	";
	try {
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':symbol'		=> $stock['Symbol'],
			':company_id'	=> $company_id,
			':exchange'		=> $stock['Market'],
			':sector_code'	=> $sector_code,
			':cusip'		=> $stock['Cusip']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

//echo $stock['Symbol']." -> ".$stock['Name']." -> ".$sector_code." -> ".$stock['Cusip'];
//die();
}

// Need to handle CA processing for symbol and CUSIP changes.  Insert a new row into stocks_symbols upon a change and mark it active (ongoing, not in this utility).

echo "Done!\n";

?>