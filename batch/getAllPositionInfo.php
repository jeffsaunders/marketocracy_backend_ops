<?php
// This commandline batch script grabs the allPositionInfo for every fund for each member who logged in in the past 30 days
// *Note - this will not run within a web browser.
error_reporting(E_ERROR);  // Just show hard errors
ini_set('display_errors', '1');  // Show 'em

// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define how many days since last logged in
$duration = 30;

// Get the memberID and last login for everyone who has logged in within the past X days (value passed and defined above) or who is flagged as a Master, Teacher, or Student
if ($duration > 0){
	$query = "
		SELECT m.member_id, m.username, m.last_login, f.promote
		FROM ".$members_table." m, ".$members_flags_table." f
		WHERE m.member_id = f.member_id
		AND (last_login > :cutoff_date OR f.promote = 1 OR f.teacher = 1 OR f.student = 1)
		GROUP BY member_id
	";
}else{  // Do 'em all
	$query = "
		SELECT member_id, username, last_login
		FROM ".$members_table."
		GROUP BY member_id
	";
}
try {
	$rsMembers = $mLink->prepare($query);
	$aValues = array(
		':cutoff_date'	=> time() - (86400 * $duration)
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
	$rsMembers->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($member = $rsMembers->fetch(PDO::FETCH_ASSOC)){

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
			':member_id' => $member['member_id']
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

		$query = "allPositionInfo|".$member['username']."|".$fund_id."|".$fund_symbol."|1";
//echo $query."\n\n";

		// Set the port number for the API call
		$port = rand(52100, 52499);

		// Execute an EXPECT script to call the API
		$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
		exec($cmd);

		// Wait a tick
//			sleep(1);

	}
}

?>
