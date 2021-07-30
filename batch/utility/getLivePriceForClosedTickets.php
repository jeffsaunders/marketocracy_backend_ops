<?php
// This commandline batch script grabs the livePrice for every fund who has had a trade ticket close since their last livePrice
// *Note - this will not run within a web browser.
die();  // Stop accidental execution.
// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Only do this if the markets are open (fudged for delay)
//if (isMarketOpen(time(), $linkID, "after")){

	// See who's had a trade ticket close since their last livePrice (legacy = 0)
	$query = "
		SELECT f.fund_id, f.fund_symbol, m.username
		FROM ".$fund_table." f,
			 ".$members_table." m,
			 ".$fund_liveprice_table." l
		WHERE f.fund_id = l.fund_id
		AND m.member_id = f.member_id
		AND l.legacy = 0
	";
	try{
		$rsLivePrice = $mLink->prepare($query);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//die($preparedQuery);
		$rsLivePrice->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Step through them
	while ($getLivePrice = $rsLivePrice->fetch(PDO::FETCH_ASSOC)){

		$query = "livePrice|0|".$getLivePrice['username']."|".$getLivePrice['fund_id']."|".$getLivePrice['fund_symbol'];
//echo $query."\n\n";

		// Set the port number for the API call
		$port = rand(52000, 52099);

		// Execute an EXPECT script to call the API
		$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
		exec($cmd);

		// Wait a tick
//		sleep(1);

	}
//}

?>
