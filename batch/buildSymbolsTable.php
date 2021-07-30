<?php
// This commandline batch script builds companies and symbols tables based on an extract of all currently listed companies in FrontBase and uses the Xignite feed to fill in the rest.
// The absense of any FrontBase Key values for a company or stock indicates it is not listed in FrontBase (and probably should be)
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Load mailer
//require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Grab all the extracted companies and symbols from FrontBase (and matched exchange_ids)
$query = "
	SELECT st.*, se.exchange_id
	FROM stock_temp_JEFF st, stock_exchanges se
	WHERE se.exchange_symbol = st.exchange
	ORDER BY symbol ASC
";
//echo $query;die();
try{
	$rsFrontbase = $mLink->prepare($query);
	$rsFrontbase->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Start a string of all the assigned symbols
$assigned_symbols = "";

// Seed company id
$company_id = 1;

// Loop through all of them and insert 'em
while($frontbase = $rsFrontbase->fetch(PDO::FETCH_ASSOC)){

	// Create company record
	$query = "
		INSERT INTO stock_companies_WORK (
			company_id,
			fb_company_key,
			company_name,
			timestamp,
			active
		)VALUES(
			:company_id,
			:fb_company_key,
			:company_name,
			UNIX_TIMESTAMP(),
			1
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':company_id'		=> $company_id,
			':fb_company_key'	=> $frontbase["identitykey"],
			':company_name'		=> $frontbase["name"]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Get the company_id just assigned
//	$company_id = $mLink->lastInsertId();

	// Get additional stock information from the Xignite feed
	$query = "
		SELECT cf.Cusip, sf.CategoryOrIndustry
		FROM cusip_feed cf, stock_feed sf
		WHERE sf.Symbol = :symbol
		AND cf.Symbol = sf.Symbol
 	";
	//echo $query;die();
	try{
		$rsXignite = $fLink->prepare($query);
		$aValues = array(
			':symbol'	=> $frontbase["symbol"]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsXignite->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$xignite = $rsXignite->fetch(PDO::FETCH_ASSOC);

	// Get the stock's sector_code
	$query = "
		SELECT sector_id, sub_sector_id
		FROM stocks_sub_sectors
		WHERE sub_sector_name = :sub_sector_name
	";
	try{
		$rsSector = $mLink->prepare($query);
		$aValues = array(
			':sub_sector_name'	=> trim($xignite["CategoryOrIndustry"])
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsSector->execute($aValues);
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

	// Now create symbol record
	$query = "
		INSERT INTO stock_symbols_WORK (
			symbol_id,
			company_id,
			symbol,
			fb_stock_key,
			exchange_id,
			sector_code,
			cusip,
			created_timestamp,
			effective_timestamp,
			status
		)VALUES(
			:symbol_id,
			:company_id,
			:symbol,
			:fb_stock_key,
			:exchange_id,
			:sector_code,
			:cusip,
			UNIX_TIMESTAMP(),
			UNIX_TIMESTAMP(),
			'active'
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':symbol_id'		=> $company_id,
			':company_id'		=> $company_id,
			':symbol'			=> $frontbase["symbol"],
			':fb_stock_key'		=> $frontbase["stockkey"],
			':exchange_id'		=> $frontbase["exchange_id"],
			':sector_code'		=> $sector_code,
			':cusip'			=> ($xignite["Cusip"] == '' ? 'N/A' : $xignite["Cusip"])
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Push the symbol onto the symbols list (used later)
	$assigned_symbols .= "'".$frontbase["symbol"]."',";

	// Increment company id
	$company_id++;

}

// -- OK, now we get the rest of the stocks in the Xignite feed (not listed in FrontBase)

// Pop off the trailing comma from the symbols list
$assigned_symbols = substr($assigned_symbols, 0, -1);

// Get the rest of the stocks
$query = "
	SELECT sf.*, cf.Cusip,
	FROM stock_feed sf, cusip_feed cf
	WHERE sf.Symbol NOT IN (".$assigned_symbols.")
	AND cf.Symbol = sf.Symbol
	";
//echo $query;die();
try{
	$rsFeed = $fLink->prepare($query);
	$rsFeed->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Get the MAX company_id and add 1 to seed
$query = "
	SELECT MAX(company_id) + 1 as next_id,
	FROM stock_companies_WORK
	";
//echo $query;die();
try{
	$rsMaxID = $fLink->prepare($query);
	$rsMaxID->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$company_id = $rsMaxID->fetchColumn();

// Loop through all of them and insert 'em
while($feed = $rsFeed->fetch(PDO::FETCH_ASSOC)){

	// Create company record
	$query = "
		INSERT INTO stock_companies_WORK (
			company_id,
			fb_company_key,
			company_name,
			timestamp,
			active
		)VALUES(
			:company_id,
			:fb_company_key,
			:company_name,
			UNIX_TIMESTAMP(),
			1
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':company_id'		=> $company_id,
			':fb_company_key'	=> $frontbase["identitykey"],
			':company_name'		=> $frontbase["name"]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Get the company_id just assigned
//	$company_id = $mLink->lastInsertId();

?>
