<?php
// This commandline batch script checks for stopped heartbeats and kills any loin sessions that have been abandoned
// *Note - this will not run within a web browser.

// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// See who's bailed on their login session
$query = "
	SELECT uid, member_id
	FROM ".$logged_in_table."
	WHERE heartbeat_timestamp < ".(time() - $inactivity_timeout)."
";
//die($query);
try{
	$rsAbandoned = $mLink->prepare($query);
	$rsAbandoned->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($abandoned = $rsAbandoned->fetch(PDO::FETCH_ASSOC)){

	// Delete the abandoned login record
	$query = "
		DELETE FROM ".$logged_in_table."
		WHERE uid = :uid
	";
	try {
		$rsDelete = $mLink->prepare($query);
		$aValues = array(
			':uid'	=> $abandoned['uid']
		);
		// Prepared query - for error logging and debugging
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
		$rsDelete->execute($aValues);
	}
	catch(PDOException $error){
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// log the event
	$query = "
		INSERT INTO ".$eventslog_table." (
			member_id,
			timestamp,
			event,
			detail
		) VALUES (
			:member_id,
			UNIX_TIMESTAMP(),
			:event,
			:detail
		)
	";
	try {
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $abandoned['member_id'],
			':event'		=> "Logout",
			':detail'		=> "heartbeat-timeout"
		);
		// Prepared query - for error logging and debugging
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

?>