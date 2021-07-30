<?php
// This script pulls trade history from inception for any passed FrontBase fund key(s).
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 3/5/18
// Modified by: Jeff Saunders - 3/12/18 - added emailing of exceptions
// Modified by:	Jeff Saunders - 3/14/18 - added exceptions flagging

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Set process date string
$sDate = date('mdy', strtotime('yesterday'));
if (isset($_REQUEST['date'])){
	$sDate = date('mdy', strtotime($_REQUEST['date']));
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
$filename = "FN".$sDate;
$zip_file = $filename.".zip";
$price_file = $filename.".MV1";

// Initialize exceptions string
$exceptions = "Symbol,Price,Company,Date,Exchange,CUSIP,Note\n";
$report = false;

// Copy zip file to a temp directory and unzip it.
copy("/mnt/foliofn/".$zip_file, "/var/www/html/batch/foliofntmp/".$zip_file);
sleep(5);  // Give the copy time to finish
chdir("/var/www/html/batch/foliofntmp/");
shell_exec("unzip -o $zip_file");

// Read the price file one line at a time
$file = new \SplFileObject($price_file);
while (!$file->eof()){

	$row = $file->fgetcsv("\t");
//print_r($row);die();

	// If the row is blank, skip it
	if (trim($row[0]) == ""){
		continue;
	}

	// Assign and massage data
	$symbol = trim($row[4]);
	$company = trim($row[9]);
	$price = $row[7];
	$date = date("Y-m-d", strtotime($row[6]));
	$unix_date = mktime(5,0,0,substr($date,5,2),substr($date,8,2),substr($date,0,4));
	$exchange = trim($row[3]);
	$cusip = trim($row[5]);
	$note = ($row[10] = ''? NULL : $row[10]);

	if (!strpos($price, '.')){
		$note = $price;
		$price = NULL;

		// See if we already have this exception flagged and it's still active (as an exception)
		$query = "
			SELECT COUNT(uid) as count
			FROM stock_closing_prices_exceptions
			WHERE symbol = :symbol
			AND end_date IS NULL
		";
		try {
			$rsException = $tLink->prepare($query);
			$aValues = array(
				':symbol' 	=> $symbol
			);
			//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;die();
			$rsException->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$exception = $rsException->fetch(PDO::FETCH_ASSOC);

		// If it's not there, insert it and report it
		if ($exception['count'] == 0){

			$query = "
				INSERT INTO stock_closing_prices_exceptions (
					symbol,
					company,
					price,
					start_date,
					unix_start_date,
					exchange,
					cusip,
					exception
				) VALUES (
					:symbol,
					:company,
					:price,
					:date,
					:unix_date,
					:exchange,
					:cusip,
					:note
				)
			";
			try{
				$rsInsert = $tLink->prepare($query);
				$aValues = array(
					':symbol' 	=> $symbol,
					':company'	=> $company,
					':price'  	=> $price,
					':date' 	=> $date,
					':unix_date'=> $unix_date,
					':exchange'	=> $exchange,
					':cusip' 	=> $cusip,
					':note' 	=> $note

				);
				//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery;die();
				$rsInsert->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				$aErrors[] = $error;
			}

			$exceptions .= $symbol.",".$price.",".$company.",".$date.",".$exchange.",".$cusip.",".$note."\n";
			$report = true;

		}

		// If it is already there, don't report it again - just fall through

	}


//echo $symbol." | ".$price." | ".$company." | ".$date." | ".$unix_date." | ".$exchange." | ".$cusip." | ".$note."\n";
//die();

	// Insert price record
	$query = "
		INSERT INTO stock_closing_prices (
			symbol,
			company,
			price,
			date,
			unix_date,
			exchange,
			cusip,
			note
		) VALUES (
			:symbol,
			:company,
			:price,
			:date,
			:unix_date,
			:exchange,
			:cusip,
			:note
		)
	";
	try{
		$rsInsert = $tLink->prepare($query);
		$aValues = array(
			':symbol' 	=> $symbol,
			':company'	=> $company,
			':price'  	=> $price,
			':date' 	=> $date,
			':unix_date'=> $unix_date,
			':exchange'	=> $exchange,
			':cusip' 	=> $cusip,
			':note' 	=> $note

		);
		//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		$aErrors[] = $error;
	}

}

// Delete temp files
// Be very specific when deleting using wildcards! (full path, etc.)
shell_exec("rm -f /var/www/html/batch/foliofntmp/$filename.*");

// If there were exceptions, email them to Ken (and me)
if ($report){

	$emailBody = "The following exceptions were detected in today's FOLIOfn closing pricing file import:\n\n".$exceptions;

	// Mail it
	$email = new PHPMailer();
	$email->From      = 'it@marketocracy.com';
	$email->FromName  = 'Marketocracy IT';
	$email->Subject   = 'FOLIOfn Closing Prices File Exceptions';
	$email->Body      = $emailBody;
	$email->AddAddress('ken.kam@marketocracy.com');
	$email->AddCC('jeff.saunders@marketocracy.com');
	//if (!$emptyReport){
	//	$email->AddAttachment('/var/www/html/tmp/ShortPositions_'.date("Ymd").'.csv');
	//}
	$email->Send();

}

?>
