<?php
/*
// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

	$query = "
		SELECT trade_processing
		FROM system_api_queue
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
echo $queue."\n";
*/
// Set starting port number, and range, for the API calls
$startPort = 52100; // API2
$endPort = 52385;
$port = $startPort;
$query = "";

while ($port <= $endPort){
//	if ($port > 52383 && $port < 52387){
//		$port = 52387;
//	}
	echo $port."\n";
	// Execute an EXPECT script to call the API
	$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	exec($cmd);

	// Increment the port number for the next API call
//	if ($port == $endPort){
//		$port = $startPort;
//	}else{
		$port++;

//	}
	// Take a short breather
//	usleep(100000);
}


?>
