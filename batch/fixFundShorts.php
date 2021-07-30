<?php
// This commandline batch script attempts to correct any erroneous fund shorts ("Display Shorts") by calling for a fresh tradesForPosition for each via the API and then rebuilding the fund stratifications.
// A subsequently executed report (getFundShorts.php) sends a list of the remaining ones to Marty.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Start me up
//session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load mailer
//require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Check for negative active positions
$lastLogin = strtotime("-1 month"); // Ignore anyone who hasn't logged in recently
$query = "
	SELECT 	p.fund_id,
			p.stockSymbol,
			f.fund_symbol,
			m.username
	FROM members_fund_stratification_basic p, members_fund f, members m
	WHERE p.totalShares < 0
	AND f.active = 1
	AND f.short_fund <> 1
	AND f.fund_id = p.fund_id
	AND m.member_id = f.member_id
	AND m.last_login > ".$lastLogin."
	ORDER BY m.member_id + 0 ASC
";
//echo $query;die();

/*
Add this under the WHERE clause above to restrict this to only active members (flagged as "active")
	AND m.active = 1
*/

try{
	$rsShortPositions = $mLink->prepare($query);
	$rsShortPositions->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	$aErrors[] = $error;
}

// Set starting port number, and range, for the API calls
$startPort = 52100; // API2
$endPort = 52499;
$port = rand($startPort, $endPort);

// Start a counter and initialize an array for the fund_ids
$shortsFound = 0;
$aFunds	= array();

// What's happening?
echo "Running tradesForPosition API calls...\n";

// Loop through all the results
while($shorts = $rsShortPositions->fetch(PDO::FETCH_ASSOC)){

	// Assign the values to variables
	$username	= trim($shorts['username']);
	$fundID		= trim($shorts['fund_id']);
	$fundSymbol	= trim($shorts['fund_symbol']);
	$stockSymbol= trim($shorts['stockSymbol']);

	// Run tradesForPosition, since inception, for each to attempt to correct them
	$query = "tradesForPosition|0|".$username."|".$fundID."|".$fundSymbol."|".$stockSymbol."|20000101";
//echo $query."\n\n";

	// Execute an EXPECT script to call the API
	$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	exec($cmd);

	// Increment the port number for the next API call
	if ($port == $endPort){
		$port = $startPort;
	}else{
		$port++;
	}

	// Push the fundID onto an array for later
	$aFunds[] = $fundID;

	// Increment counter
	$shortsFound++;

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

echo "Done!\n".$shortsFound." Shorts Found in ".count($aFundsDeDuped)." Funds.\n\n";

?>