<?php
// This script pulls trade history from inception for any passed FrontBase fund key(s).
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders - 8/29/18
// Modified by: Jeff Saunders - x/x/xx - added blah blah

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

// Get passed file
if (isset($_REQUEST['file'])){

	// Does it exists?
	if (file_exists($_REQUEST['file'])){
		$filename = $_REQUEST['file'];
	}else{
		echo "File ".$_REQUEST['file']." Does Not Exist - ABORTED.";
		die("\n");
	}
}else{
	echo 'You Must Pass A File Name (e.g. "file=/path/to/filename") - ABORTED.';
	die("\n");
}

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Set starting port number, and range, for the API calls
$startPort = 52100; // API2
$endPort = 52499;
$port = rand($startPort, $endPort);

// Initialize array(s)
$aFunds = array();

// What's happening?
echo "Running tradesForFund API calls...\n";

// Read the fundKey file one line at a time
$file = new \SplFileObject($filename);
while (!$file->eof()){

	$row = $file->fgetcsv("\t");
	$fundKey = $row[0];

	$query = "
		SELECT m.username, f.fund_id, f.fund_symbol
		FROM members m, members_fund f
		WHERE f.fb_primarykey = \"".trim($fundKey)."\"
		AND m.member_id = f.member_id
	";
	// Had to use ol' fashioned inline var replacement as PDO substitution broke due to the embedded single quotes in the value
	try {
		$rsFundInfo = $mLink->prepare($query);
		$aValues = array(
			':fundKey' 	=> $fundKey
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		echo $preparedQuery;//die();
		$rsFundInfo->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
//dump_rs($rsFundInfo);
	$fund = $rsFundInfo->fetch(PDO::FETCH_ASSOC);

	// Build string to pull trade history since inception
	$apiQuery = "tradesForFund|0|".$fund['username']."|".$fund['fund_id']."|".$fund['fund_symbol']."|20000101";
//echo $apiQuery;die();
	// Execute an EXPECT script to call the API
	$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$apiQuery.'" > /dev/null &';
	exec($cmd);

	// Increment the port number for the next API call
	if ($port == $endPort){
		$port = $startPort;
	}else{
		$port++;
	}

	// Push the fundID onto an array for later
	$aFunds[] = $fund['fund_id'];

	// Take a short breather
	//usleep(100000); // 0.1 seconds

}

// Take a breather to let the API get started (if the number of funds is small)
sleep(15);

// What's happening?
echo "Waiting for API calls to finish...\n";

// Watch the queue
while (true){
	$query = "
		SELECT trade_processing
		FROM system_api_queue
		WHERE api = 'API2'
	";
	try{
		$rsTradeQueue = $mLink->prepare($query);
		$rsTradeQueue->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		$aErrors[] = $error;
	}
	$tradeQueue = $rsTradeQueue->fetch(PDO::FETCH_ASSOC);

	// Pull out the number of input files in the queue
	$aQueue = explode('|', $tradeQueue['trade_processing']);
	$queue = $aQueue[0];

	// if it's reached zero, bail out of the while loop
	if ($queue == 0){
		break;
	}

	// Take a breather
	sleep(15);

}

// What's happening?
echo "Rebuilding Stratifications...\n";

// De-dupe the fundID array - only need to rebuild each fund once, regardless of how many positions were repaired
$aFundsDeDuped = array_unique($aFunds);

// Start a counter of for how many strat-build's have spawned
$counter = 0;

//loop through the affected fundIDs
foreach($aFundsDeDuped as $key=>$fundID){

	// Perform a stratification rebuild
	$cmd = '/usr/bin/php /var/www/html/scripts/strat-build.php "fundID='.$fundID.'" > /dev/null &';
//echo $cmd."\n";
	exec($cmd);

	// Take a breather to let the number of DB connections settle
	$counter++;
	if ($counter == 100){
		sleep(15);
		$counter = 0;
	}

}

echo "Done!\n\n";









//die();
/*
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
//				$rsInsert->execute($aValues);
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
//		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		$aErrors[] = $error;
	}

}

// Delete temp files
// Be very specific when deleting using wildcards! (full path, etc.)
//shell_exec("rm -f /var/www/html/batch/foliofntmp/$filename.*");

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
