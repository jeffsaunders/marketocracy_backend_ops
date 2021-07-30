<?php
// This script pulls periodic NAVs for all active funds for ranking purposes.
// It replaces the same-named Python script that used to pull the same data from Legacy FrontBase
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 4/11/18
// Modified by: Jeff Saunders - 11/2/18 - pulling data from both folio and portfolio tables, folio records supersede portfolio records

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

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

//Determine dates to process
$sDate = date('Ymd', strtotime('yesterday'));
if (isset($_REQUEST['date'])){
	$sDate = date('Ymd', strtotime($_REQUEST['date']));
}
$pDate = date('n/j/Y', strtotime($sDate));

// X years ago
$sDate_1 = date('Ymd', strtotime($sDate . " -1 year"));
$sDate_3 = date('Ymd', strtotime($sDate . " -3 years"));
$sDate_5 = date('Ymd', strtotime($sDate . " -5 years"));
$sDate_10 = date('Ymd', strtotime($sDate . " -10 years"));
$sDate_15 = date('Ymd', strtotime($sDate . " -15 years"));

// Previous month's end
$sDate_ME = date('Ymd', strtotime("last day of previous month", strtotime($sDate)));

# Previous quarter's end (simple month comparison method)
if (date("n", strtotime($sDate)) < 4) {
	$sDate_QE = date('Ymd', strtotime('last day of december', strtotime($sDate . " -1 year")));
}elseif (date("n", strtotime($sDate)) < 7) {
	$sDate_QE = date('Ymd', strtotime('last day of march', strtotime($sDate)));
}elseif (date("n", strtotime($sDate)) < 10) {
	$sDate_QE = date('Ymd', strtotime('last day of june', strtotime($sDate)));
}else {
	$sDate_QE = date('Ymd', strtotime('last day of september', strtotime($sDate)));
}

// Previous year's end
$sDate_YE = date('Ymd', strtotime('last day of december', strtotime($sDate . " -1 year")));

// Build an array of the applicable dates
$aDates = array("date"=>$sDate,"date_1"=>$sDate_1,"date_3"=>$sDate_3,"date_5"=>$sDate_5,"date_10"=>$sDate_10,"date_15"=>$sDate_15,"date_me"=>$sDate_ME,"date_qe"=>$sDate_QE,"date_ye"=>$sDate_YE);
//print_r($aDates);

// Build a string from that array for use in subsequent queries
$sDates = "'" . implode("','", $aDates) . "'";
//echo $sDates;

//die();

/*
echo $sDate_1."\n";
echo $sDate_3."\n";
echo $sDate_5."\n";
echo $sDate_10."\n";
echo $sDate_15."\n\n";
echo $sDate_ME."\n";
echo $sDate_QE."\n";
//echo date("n", strtotime($sDate));
echo $sDate_YE."\n";

$sDates = "'" . $sDate_1 . "','" . $sDate_3 . "','" . $sDate_5 . "','" . $sDate_10 . "','" . $sDate_15 . "','" . $sDate_ME . "','" . $sDate_QE . "','" . $sDate_YE . "'";

*/



// Create the output files
$filename = "NAV_history_output_".$sDate.".csv";
$fp = fopen("/var/www/html/tmp/".$filename, "w");

// Write header rows
$headers = "FundID,MemberID,AsOfDate,NAV,NAV_1,NAV_3,NAV_5,NAV_10,NAV_15,NAV_ME,NAV_QE,NAV_YE\r\n";
fwrite($fp, $headers);

// Get the active fund IDs
$query = "
	SELECT fund_id
	FROM members_fund
	WHERE active = 1
	AND short_fund = 0
	AND member_id > 0
	ORDER BY member_id + 0 ASC, unix_date ASC
";
try {
	$rsFundIDs = $mLink->prepare($query);
	$rsFundIDs->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through the funds
while($fund = $rsFundIDs->fetch(PDO::FETCH_ASSOC)){

	// Assign some values
	$fundID = $fund['fund_id'];
	$memberID = explode("-", $fundID)[0];

	// Get the funds NAVs on the specified dates
//	$query = "
//		SELECT date, (totalValue/shares) AS nav
//		FROM members_fund_pricing
//		WHERE fund_id = :fund
//		AND date IN (:dates)
//	";
	// For some reason PDO substitution seems to break here, so reverting to inline substitution
	$query = "
		SELECT date, (totalValue/shares) AS nav
		FROM members_fund_pricing
		WHERE fund_id = '".$fundID."'
		AND date IN (".$sDates.")
	";
	try {
		$rsNAVs = $mLink->prepare($query);
//		$aValues = array(
//			':fund' 	=> $fundID,
//			':dates' 	=> $sDates
//		);
//		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
//		$rsNAVs->execute($aValues);
		$rsNAVs->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

//dump_rs($rsNAVs);

	// Build an array of the NAVs, keyed by date
	$aNAVs = array('foo'=>'bar');
	while($row = $rsNAVs->fetch(PDO::FETCH_ASSOC)){
		$aNAVs += [$row['date'] => $row['nav']];
	}
//print_r($aNAVs);

// step through $aDates array, printing the value then comma for each element
//headers = "FundKey,ManagerKey,AsOfDate,NAV,NAV_1,NAV_3,NAV_5,NAV_10,NAV_15,NAV_ME,NAV_QE,NAV_YE\r\n"
	$sRow = $fundID . "," . $memberID . "," . $pDate . ",";
	foreach($aDates as $key=>$date){
		$sRow .= $aNAVs[$date] . ",";
	}

	// Pop the trailing comma off
	$sRow = substr($sRow, 0, -1);

	// Add CRLF
	$sRow .= "\r\n";

	// write that line to the file
	fwrite($fp, $sRow);

//echo $sRow;die();

}

// Close 'er up
fclose($fp);


die();














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
