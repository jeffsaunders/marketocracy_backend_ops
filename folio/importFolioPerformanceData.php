<?php
// This script calculates and inserts data from the FOLIOfn Model Performance Chart into the folio_fund_pricing table.
// it reads a CSV file downloaded from FOLIOfn and inserts the data for each day found.  CSV filename must be passed.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 3/15/18
// Modified by: Jeff Saunders - 3/23/18 - Modified the validDate() function to use leading zero date format
// Modified by: Jeff Saunders - 11/30/18 - Modified to utilize new folio_folio_names table to determine fund IDs by FOLIOfn's "Folio Number"
// Modified by: Jeff Saunders - 12/3/18 - Modified to automatically execute the web based MTM process via CURL
// Modified by: Jeff Saunders - 6/23/20 - Modified to access web based MTM process via mytrackrecord.com - used to use marketaco.com but that domain has been let to expire

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

// Define local functions
function validDate($date, $format = 'm/d/Y')
{
	$d = DateTime::createFromFormat($format, $date);
	// The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
	return $d && $d->format($format) === $date;
}

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Set csv file name
$path = "/var/www/html/folio/tmp/";
if (isset($_REQUEST['file'])){
	if (file_exists($path.$_REQUEST['file'])){
		$filename = $path.$_REQUEST['file'];
	}else{
		echo 'The File '.$path.$_REQUEST['file'].' Does Not Exist.  Make Sure File Is Uploaded And Check Filename Spelling - ABORTED.';
		die("\n\n");
	}
}else{
	echo 'You Must Pass A Valid CSV filename to Process (e.g. "file=managername-performance-download-10152018.csv") - ABORTED.';
	die("\n\n");
}

//echo $path.$_REQUEST['file']."\n";die();

// Initialize the file object for processing
$file = new SplFileObject($filename);

// Set the default folio name - will change as the file is traversed
//$folio = "";

 while (!$file->eof()){

 	// Read the file, line by line, turning each line into an array
 	$row = $file->fgetcsv(",");

//	// Get the folio name (test first cell of each row)
//	if (substr($row[0], 0, 11) == "Folio Name:"){
//		$folio = substr($row[0], 13);
//		continue;
//	}

	// Get the folio number (test first cell of each row)
	if (substr($row[0], 0, 13) == "Folio Number:"){
		$folio = substr($row[0], 15);
		continue;
	}

	// Skip any rows that don't start with a date
	if (!validDate(trim($row[0]))){
		continue;
	}

//echo "<pre>";print_r($row);echo "</pre>";//die();
//echo $folio."\n";//die();

	// Set some values from the row
	$processDate = date('Ymd', strtotime($row[0]));
	$unixDate = strtotime($row[0]);
	$totalValue = $row[1];

	// set $lastDate as the first date found - CSV in reverse order
	if (!isset($lastDate)){
		$lastDate = $unixDate;
	}
//echo $lastDate."\n";
	// If we passed the fund ID, set it
	if (isset($_REQUEST['fund'])){
		$fundID = $_REQUEST['fund'];
	}

	// If fund ID is not set, get it
	if (!isset($fundID)){

/*		// Get Fund ID
		$query = "
			SELECT fund_id
			FROM folio_trade_history
			WHERE folio = :folio
			OR folio = :folioMod
			ORDER BY unix_date DESC
			LIMIT 1
		";
		try{
			$rsFundID = $tLink->prepare($query);
			$aValues = array(
				':folio'	=> $folio,
				':folioMod'	=> str_replace('FOLIOfn:', 'mFOLIO:', $folio)
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery."\n";//die();
			$rsFundID->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
*/
		// Get Fund ID
		$query = "
			SELECT fund_id
			FROM folio_folio_names
			WHERE folio_number = :folio
		";
		try{
			$rsFundID = $tLink->prepare($query);
			$aValues = array(
				':folio'	=> $folio
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery."\n";//die();
			$rsFundID->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$fundID = $rsFundID->fetchColumn();

		// If the found fundID has an X in it (indicating old model), pop it off
		if (strpos($fundID, 'X') !== false){
			$fundID = substr($fundID, 0, -1);
		}
//echo $fundID."\n";//die();

	}

	// If we still don't have a fund ID, bail out
	if (!isset($fundID)){
		echo 'Unable to Determine Fund ID.  Check folio_names Table or Pass Value (e.g. "&fund=123-1") - ABORTED.';
		die("\n\n");
	}

	// Extract the memberID from the fundID
	$memberID = explode("-", $fundID)[0];

	// See if we have at least one pricing record (start record)
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
		//echo $preparedQuery."\n";die();
		$rsCount->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$records = $rsCount->fetchColumn();

	if ($records < 1){
		echo 'The Fund ID For The File You Passed Has No Pricing Records, Run calc-start Process For Fund '.$fundID.' - ABORTED.';
		die("\n\n");
	}

	// Get the shares quantity for price calculation (NAV fudge value)
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

//echo $processDate."\n";echo $shares."\n";echo $price."\n\n";//die();

	// Insert pricing record
//	$query = "
//		INSERT INTO folio_fund_pricing (
//			fund_id,
//			timestamp,
//			date,
//			unix_date,
//			totalValue,
//			price,
//			shares
//		) VALUES (
//			:fund_id,
//			UNIX_TIMESTAMP(),
//			:date,
//			:unix_date,
//			:value,
//			:price,
//			:shares
//		)
//	";

	// Insert the row if one doesn't already exist
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
		) SELECT * FROM (
			SELECT
			:member_id,
			:fund_id,
			UNIX_TIMESTAMP(),
			:date,
			:unix_date,
			:value,
			:price,
			:shares
		) AS tmp WHERE NOT EXISTS (
			SELECT *
			FROM folio_fund_pricing
			WHERE fund_id = :fund_id
			AND date = :date
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
		//echo $preparedQuery;//die();
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		$aErrors[] = $error;
	}


}

// Perform an MTM rebuild via CURL.  Must use HTTP, not SSL - Exception defined in destination .htaccess
// Replace this with commandline version...
$url = "http://process.mytrackrecord.com/process/build-mtm3.php?managers=".$memberID."&month=".date('n', $lastDate)."&day=".date('j', $lastDate)."&year=".date('Y', $lastDate);
//echo $url."\n";
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$mtm = curl_exec($curl);
curl_close($curl);
//echo $mtm;

echo "Done!\n";

?>
