<?php
// This script extracts client info from the FOLIOfn feed and imports it into the folio_clients table.
// This is run as a CRON job every morning, after yesterday's FOLIOfn feed file arrives.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 3/5/18
// #Modified by:	Jeff Saunders - 3/12/18 - added emailing of exceptions
// #Modified by:	Jeff Saunders - 3/14/18 - added exceptions flagging
// #Modified by:	Jeff Saunders - 8/14/18 - Changed PDO connection to beef up reliability
// #Modified by:	Jeff Saunders - 8/16/18 - Added data source tracking, set to "FOLIOfn" from this script
// Modified by:	Jeff Saunders - 5/13/18 - Updated the code that used to extract the daily closing prices (importFolioPrices.php) for this purpose


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

// Encryption functions
function encrypt($value, $key='SGFwcHkgQmlydGhkYXkgUmFjaGVsIQ=='){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$encryptedValue = mcrypt_generic($td, $value);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return base64_encode( $encryptedValue );
}

function double_encrypt($value, $key='fhPXaYlnraw4aN6mOVOfOXPdEtVQGZml'){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$encryptedValue = mcrypt_generic($td, $value);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return base64_encode( $encryptedValue );
}

// Connect to MySQL
//require("/var/www/html/includes/dbConnectPDO.php");
// Just connect to the one DB instead - trying to avoid multiple connection resource issues (plus add retries code)
$dbHost = "192.168.111.211";
$dbName = "mtr_marketaco";
$dbUser = "marketocracy";
$dbPass = "KfabyZcbE3";

//Connect to mtr_marketaco DB / MySQL with PDO_MYSQL
$retries = 5;
while ($retries > 0){
	try{
		$tLink = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
		$tLink->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		break;
	}
	catch(PDOException $error){
		echo "Connection Failed on Try #".$retries." - Retrying\n";
		// Wait a sec then retry
		sleep(1);
		$retries--;
	}
}
if ($retries == 0){ // Never got connected!
	// Log any error to /var/log/httpd/portfolio-pdo_log
	file_put_contents("/var/log/httpd/portfolio-pdo_log", "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	die($error->getMessage());
}

// Get newest system config values
//require("/var/www/html/includes/getConfigPDO.php");

// Load mailer
//require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Build extract filenames
$filename = "FN".$sDate;
$zip_file = $filename.".zip";
$client_file = $filename.".ID1";

// Initialize exceptions string
//$exceptions = "Symbol,Price,Company,Date,Exchange,CUSIP,Note\n";
//$report = false;

// Copy zip file to a temp directory and unzip it.
copy("/mnt/foliofn/MARKET691752/".$zip_file, "/var/www/html/folio/tmp/".$zip_file);
sleep(5);  // Give the copy time to finish
chdir("/var/www/html/folio/tmp/");
shell_exec("unzip -o $zip_file");

// Insert the raw data
$query = "
	LOAD DATA LOCAL INFILE :client_file
	INTO TABLE folio_clients
	FIELDS TERMINATED BY '\t'
	OPTIONALLY ENCLOSED BY '\"'
	LINES TERMINATED BY '\n'
";
try{
	$rsImport = $tLink->prepare($query);
	$aValues = array(
		':client_file' 	=> "/var/www/html/folio/tmp/".$client_file
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;
	$rsImport->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	$aErrors[] = $error;
	print_r($aErrors);
}

// Process the raw data (get the member/fund IDs)
$query = "
	SELECT folio_name, tax_id, tax_id_2
	FROM folio_clients
	WHERE processed = 0
	GROUP BY folio_name
";
try {
	$rsFolioNames = $tLink->prepare($query);
	$rsFolioNames->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($names = $rsFolioNames->fetch(PDO::FETCH_ASSOC)){

	// Get the fund IDs
	$query = "
		SELECT fund_id
		FROM folio_folio_names
		WHERE folio_name = :folio_name
		OR alt_name_1 = :folio_name
		OR alt_name_2 = :folio_name
		OR alt_name_3 = :folio_name
		OR alt_name_4 = :folio_name
		OR alt_name_5 = :folio_name
		GROUP BY fund_id
	";
	try {
		$rsFundID = $tLink->prepare($query);
		$aValues = array(
			':folio_name' 	=> $names['folio_name']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;
		$rsFundID->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Let's assign some values based on the results
	$fundID				= $rsFundID->fetchColumn();
//echo $fundID."\n";
	$memberID			= explode("-", $fundID)[0]; //Extract the memberID from the fundID
	$folio_name			= $names['folio_name'];
	$encrypted_tax_id	= encrypt($names['tax_id']);
	$encrypted_tax_id_2	= encrypt($names['tax_id_2']);

	// Update the clients table
	$query = "
		UPDATE folio_clients
		SET tax_id = :encrypted_tax_id,
			tax_id_2 = :encrypted_tax_id_2,
			member_id = :member_id,
			fund_id = :fund_id,
			processed = 1
		WHERE folio_name = :folio_name
		AND processed = 0
	";
	try{
		$rsUpdate = $tLink->prepare($query);
		$aValues = array(
			':encrypted_tax_id'		=> $encrypted_tax_id,
			':encrypted_tax_id_2'	=> $encrypted_tax_id_2,
			':member_id'			=> ($memberID != '' ? $memberID : 'NULL'),
			':fund_id'				=> ($fundID != '' ? $fundID : 'NULL'),
			':folio_name'			=> $folio_name
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		echo $preparedQuery;//die();
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
echo $error;
	}

}



// If we got a fund ID{
	// explode it to get the member ID
	// update the clients table to add the IDs where folio_name = $name['folio_name']
	// flag the row as processed too
// }

// if we didn't get a hit, just flag it as processed and leave the IDs NULL

// In either case, encrypt the SSNs and update them in the table




/*

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
	$price = trim($row[7]);
	$date = date("Y-m-d", strtotime($row[6]));
	$unix_date = mktime(5,0,0,substr($date,5,2),substr($date,8,2),substr($date,0,4));
	$exchange = trim($row[3]);
	$cusip = trim($row[5]);
	$note = ($row[10] = ''? NULL : trim($row[10]));

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
			note,
			source
		) VALUES (
			:symbol,
			:company,
			:price,
			:date,
			:unix_date,
			:exchange,
			:cusip,
			:note,
			:source
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
			':note' 	=> $note,
			':source' 	=> "FOLIOfn"

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
shell_exec("rm -f /var/www/html/folio/tmp/$filename.*");

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
*/
?>
