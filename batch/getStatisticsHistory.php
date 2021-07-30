<?php
// This commandline batch script grabs the fund statistics for the last day of the month for all tracked funds of tracked managers
// Updated to include all Pro level members, not just tracked managers - 11/17/17 JSS
// *Note - this will not run within a web browser.

// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Help screen if they pass "help" as a parameter
if (isset($_REQUEST['help'])){
	echo
'
Valid Parameters:

date:		Defaults to "yesterday".  Passing a date in YYYYMMDD format will run for that date.

example:	php getStatisticsHistory.php "date=20141231"
		Not specifying a date results in it assuming '.date("Ymd", strtotime("yesterday")).'.

';
	die(); // Just display message and quit
}

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Set the starting port number for the API calls
$port = rand(52100, 52499);

// Assign date
$date = date("Ymd", strtotime("yesterday"));
//$date = "20150630";
//$date = "20161231";
if (isset($_REQUEST['date']) && is_numeric($_REQUEST['date'])){
	// Trusting that it's in the correct format (YYYYMMDD)
	$date = $_REQUEST['date'];
}

// Get the member IDs of all the Pro level members (including managers - product_id 10)
$query = "
	SELECT member_id
	FROM members_subscriptions
	WHERE active = 1
	AND product_id IN (3,4,10,11)
	ORDER BY member_id ASC
";
try{
	$rs_tracking = $mLink->prepare($query);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
	$rs_tracking->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($tracking = $rs_tracking->fetch(PDO::FETCH_ASSOC)){

	// Get their login name
	$query = "
		SELECT username
		FROM ".$members_table."
		WHERE member_id = :member_id
	";
	try {
		$rs_member = $mLink->prepare($query);
		$aValues = array(
			':member_id' => $tracking['member_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rs_member->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$member = $rs_member->fetch(PDO::FETCH_ASSOC);

	// Get their fund's IDs and symbols
	$query = "
		SELECT fund_id, fund_symbol
		FROM ".$fund_table."
		WHERE member_id = :member_id
		AND active = 1
	";
	try {
		$rs_funds = $mLink->prepare($query);
		$aValues = array(
			':member_id' => $tracking['member_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rs_funds->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	while ($fund = $rs_funds->fetch(PDO::FETCH_ASSOC)){

		$aMethods = array("aggregateStatistics","alphaBetaStatistics");
		for ($cnt = 0; $cnt < sizeof($aMethods); $cnt++){

			// Build queries and submit them
			$query = $aMethods[$cnt]."|0|".$member['username']."|".$fund['fund_id']."H|".$fund['fund_symbol']."|".$date;

//aggregateStatistics|0|jeffsaunders|1-1H|JMF|20150131
//aggregateStatistics|0|adevkota|4272-1H|AMF|20171031
//alphaBetaStatistics|0|adevkota|4272-1H|AMF|20171031
//alphaBetaStatistics|0|adevkota|4272-5H|AMFSP|20171031
//echo $query."\n\n";

			// Set the port number for the API call
			if ($port == 52499){ // Last port #, roll over
				$port = 52100;
			}else{
				$port++;
			}

			// Execute an EXPECT script to call the API
			$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
			exec($cmd);
//echo $cmd."\n";
			// Wait a tick
//			sleep(1);
		}
	}
}

?>
