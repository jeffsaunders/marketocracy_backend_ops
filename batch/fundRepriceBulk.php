<?php
// This script reprices every active fund from the date passed forward.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders - 9/5/18
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
//echo (strtotime($_REQUEST['date']) != false ? strtotime($_REQUEST['date']) : 'False');die();
// Get passed file
if (isset($_REQUEST['date'])){
	$sDate = date('mdy', strtotime($_REQUEST['date']));
	// Does the passed date convert to a proper unixdate?
	if (!strtotime($_REQUEST['date'])){
		echo "Invalid Date Passed: ".$_REQUEST['date']." Try Formatting as YYYYMMDD - ABORTED.";
		die("\n");
	}
}else{
	echo 'You Must Pass A Valid Date (e.g. "date=20180905") - ABORTED.';
	die("\n");
}
/*
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
		//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
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
*/
?>
