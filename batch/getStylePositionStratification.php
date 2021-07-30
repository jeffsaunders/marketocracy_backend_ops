<?php
// This commandline batch script grabs the stylePositionStratification for every fund for each member who is currently logged in
// *Note - this will not run within a web browser.

// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Only do this if the markets are open (fudged for delay)
if (isMarketOpen(time(), $linkID, "after")){

	// See who's logged in
	$query = "
		SELECT DISTINCT member_id, username
		FROM ".$logged_in_table."
	";
	try{
		$rsLoggedIn = $mLink->prepare($query);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
		$rsLoggedIn->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Step through them
	while ($loggedIn = $rsLoggedIn->fetch(PDO::FETCH_ASSOC)){

		// Get their funds
		$query = "
			SELECT fund_id, fund_symbol
			FROM ".$fund_table."
			WHERE member_id = :member_id
			AND active = 1
		";
		try {
			$rsFunds = $mLink->prepare($query);
			$aValues = array(
				':member_id' => $loggedIn['member_id']
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
			$rsFunds->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Step through them and assign them to an array ($key=fundID, $value=fundSymbol)
		$aFunds = array();
		while ($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){
			$aFunds[$fund['fund_id']] = $fund['fund_symbol'];
		}
//print_r($aFunds);//die();

		// Step through the funds array and grab a livePrice for each
		foreach ($aFunds as $fund_id => $fund_symbol){

			$query = "stylePositionStratification|".$loggedIn['username']."|".$fund_id."|".$fund_symbol;
//echo $query."\n\n";

			// Set the port number for the API call
			$port = rand(52000, 52099);

			// Execute an EXPECT script to call the API
			$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
			exec($cmd);

			// Wait a tick
			sleep(1);

		}
	}
}

?>
