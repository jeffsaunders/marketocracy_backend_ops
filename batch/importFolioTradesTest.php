<?php
// This script extracts the daily trades from the FOLIOfn feed and imports them into the folio trade history table.
// This is run as a CRON job every morning, after yesterday's FOLIOfn feed file arrives.
// Passing a date value is optional and will override the default of "yesterday" (e.g. /usr/bin/php /var/www/html/batch/importFolioTrades.php "date=20180815")
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 5/30/18
// Modified by: Jeff Saunders - 8/15/18 - Added ability to pass a date and identified the need to create new memberships and funds when a folio value is not found

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Set process date string
$sDate = date('Y-m-d', strtotime('yesterday'));
if (isset($_REQUEST['date'])){
	$sDate = date('Y-m-d', strtotime($_REQUEST['date']));
}

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Build extract filenames
//$filename = "marketocracy-report-".$sDate.".csv";
$filename = "marketocracy-report-2018-08-28-new-initial.csv";

// Initialize the file object for processing
$file = new SplFileObject('/mnt/foliofn/modelTradingActivities/'.$filename);

// Set the default folio name - will change as the file is traversed
$folio = "";

while (!$file->eof()){

	// Read the file, line by line, turning each line into an array
	$row = $file->fgetcsv(",");

	// If it's the header row or the row is blank, skip it
	if (trim($row[0]) == "Loginid" || trim($row[0]) == ""){
		continue;
	}

	// If the record is not for the date being processed, skip it (I know, I could have added the condition above...whatever)
//	if (trim($row[1]) != $sDate){
//		continue;
//	}

	// $row[9] is the Folio name value
	if ($row[9] != $folio){

		// Extract the manager's username and fund symbol from the first non-header row (element 8 of the second row)
//		$username = explode("_", explode(': ', $aUpload[1][9])[1])[0];
//		$fund_symbol = explode("_", explode(': ', $aUpload[1][9])[1])[1];

		// Turns out some usernames have underscores, so we need to go about this a bit differently...
		$sFolio = explode(':', $row[9]); // Grab just the fund name, to the right of the colon
		$aParts = explode('_', trim($sFolio[1]));  // Blow the whole damn thing up
		$fund_symbol = array_pop($aParts);  // Pop off the last element - it's the fund symbol so assign it at the same time
		$username = implode('_', $aParts);  // Cram the rest back into a string with any removed underscores reinserted - that's the username, with or without any previously removed underscores.

		// Look up the member ID and "stuff" if the username is not blank - some records in the file are informational (no valid folio name), this effectively skips them
		if ($username != ""){

			// Get additional information about this manager needed for the history records (member and fund IDs)
			$query = "
				SELECT m.member_id, f.fund_id
				FROM members m, members_fund f
				WHERE m.username = :username
				AND f.fund_symbol = :fund_symbol
				AND m.member_id = f.member_id
			";
			try{
				$rsIDs = $tLink->prepare($query);
				$aValues = array(
					':username'		=> $username,
					':fund_symbol'	=> $fund_symbol
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery;//die();
				$rsIDs->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
			$ID = $rsIDs->fetch(PDO::FETCH_ASSOC);

/////////////////////////////// If not found need to create member and/or fund HERE - new fund created on Folio side.

			$member_id = $ID['member_id'];
			$fund_id = $ID['fund_id'];
			$folio = $row[9];
/*
echo $username."|";
echo $fund_symbol."|";
echo $member_id."|";
echo $fund_id."\n";
die();
*/
		}
	}

/* Sample
Array
(
	[0] => mcmtrader
	[1] => 2018-08-10
	[2] => Sells
	[3] => Paper Trade
	[4] => HYGS
	[5] => HYDROGENICS CORP NEW
	[6] => 6.25
	[7] => 1826.87177
	[8] => 11417.94856
	[9] => mFOLIO: huyehara_HMF
	[10] => 0.0
	[11] =>
)
*/

	// Get raw data
	$rawDate = $row[1];
	$rawTransaction = $row[2];
	$rawType = $row[3];
	$rawSymbol = $row[4];
	$rawName =  $row[5];
	$rawPrice = $row[6];
	$rawQuantity = $row[7];
	$rawAmount = $row[8];
	$rawFolio = $row[9];
	$rawCommission = $row[10];
	$rawNotes = $row[11];

	// Massage it
	$date = trim($rawDate);
	list($year, $month, $day) = explode('-', $date);  // Extract the date elements
	$unix_date = mktime(4, 0, 0, $month, $day, $year);

	$transaction = trim($rawTransaction);
	$trans_type = trim($rawType);
	$stock_symbol = trim($rawSymbol);
	$company_name = trim($rawName);
	$price = $rawPrice;
	$quantity = $rawQuantity;
	$amount = $rawAmount;
	$folio = trim($rawFolio);
	$commission = $rawCommission;
	$notes = $rawNotes;
/*
echo $member_id."|";
echo $fund_id."|";
echo $unix_date."|";
echo $date."|";
echo $transaction."|";
echo $trans_type."|";
echo $stock_symbol."|";
echo $company_name."|";
echo $price."|";
echo $quantity."|";
echo $amount."|";
echo $folio."|";
echo $commission."|";
echo $notes."\n";
*/
	// Insert it
	$query = "
		INSERT INTO folio_trade_history_jeff (
			member_id,
			fund_id,
			unix_date,
			date,
			transaction,
			trans_type,
			stock_symbol,
			company_name,
			price,
			quantity,
			amount,
			folio,
			commission,
			notes,
			timestamp
		)VALUES(
			:member_id,
			:fund_id,
			:unix_date,
			:date,
			:transaction,
			:trans_type,
			:stock_symbol,
			:company_name,
			:price,
			:quantity,
			:amount,
			:folio,
			:commission,
			:notes,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $tLink->prepare($query);
		$aValues = array(
			':member_id'	=> $member_id,
			':fund_id'		=> $fund_id,
			':unix_date'	=> $unix_date,
			':date'			=> $date,
			':transaction'	=> $transaction,
			':trans_type'	=> $trans_type,
			':stock_symbol'	=> $stock_symbol,
			':company_name'	=> $company_name,
			':price'		=> $price,
			':quantity'		=> $quantity,
			':amount'		=> $amount,
			':folio'		=> $folio,
			':commission'	=> $commission,
			':notes'		=> $notes
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";//die();
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Rinse, repeat
}

// c'est fini

?>
