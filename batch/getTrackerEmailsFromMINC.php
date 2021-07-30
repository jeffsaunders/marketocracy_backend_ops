<?php
// This script creates a separate database row for each manager tracked via the MINC system, rather than one row with all the managers comma delimited in one column as it's stored natively.
// It's a "one-off" but could be adapted as needed.
// *Note - this will not run within a web browser.

// Define some system settings
date_default_timezone_set('America/New_York');

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

// Get all the tracker emails records stored in the temporary table from minc
$query = "
	SELECT *
	FROM tracker_emails_from_minc_raw
";
try {
	$rsTrackers = $rLink->prepare($query);
	$rsTrackers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($tracker = $rsTrackers->fetch(PDO::FETCH_ASSOC)){

	// Explode the manager names into an array
	// ...but first remove any trailing spaces after the commas (some have spaces, some don't
	$sManagers = str_replace(", ", ",", $tracker["track"]);
	$aManagerNames = explode(",", $sManagers);

	// Loop through all the managers followed
 	for ($cnt = 0; $cnt < sizeof($aManagerNames); $cnt++){

		// Insert one row for each manager followed
		$query = "
			INSERT INTO tracker_emails_from_minc (
				email,
				first_name,
				track
			) VALUES (
				:email,
				:first_name,
				:track
			)
		";
		try{
			$rsInsert = $rLink->prepare($query);
			$aValues = array(
				':email'		=> $tracker["email"],
				':first_name'	=> $tracker["first_name"],
				':track'		=> $aManagerNames[$cnt]
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

}
?>
