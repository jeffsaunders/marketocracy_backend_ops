<?php
// This script creates the needed tables (if they don't exist) in the mtr_marketaco database, truncates the ones that do exist, and imports the complete history of each manager for the new MTR site.
// *Note - this will not run within a web browser.

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run long enough
set_time_limit(0); // Run forever
ignore_user_abort(1);  // This prevents MySQL timeouts form killing the script

// Start me up
//session_start();

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Get the member IDs for all the Managers with composite funds as of the most recent ranking period
$query = "
	SELECT member_id
	FROM rank_report_pro
	WHERE composite = 'yes'
	AND as_of_date = (SELECT MAX(as_of_date) FROM rank_report_pro)
	ORDER BY member_id + 0 ASC
";
try {
	$rsManagers = $mLink->prepare($query);
	$rsManagers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Create the destination tables (if they don't already exist)
$aQueries = array(
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members LIKE portfolio.members",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund LIKE portfolio.members_fund",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_aggregate LIKE portfolio.members_fund_aggregate",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_aggregate_history LIKE portfolio.members_fund_aggregate_history",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_composite LIKE portfolio.members_fund_composite",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_liveprice LIKE portfolio.members_fund_liveprice",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_month_to_month LIKE portfolio.members_fund_month_to_month",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_positions LIKE portfolio.members_fund_positions",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_positions_details LIKE portfolio.members_fund_positions_details",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_pricing LIKE portfolio.members_fund_pricing",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_settings LIKE portfolio.members_fund_settings",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_stratification_basic LIKE portfolio.members_fund_stratification_basic",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_stratification_sector LIKE portfolio.members_fund_stratification_sector",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_stratification_sector_positions LIKE portfolio.members_fund_stratification_sector_positions",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_stratification_style LIKE portfolio.members_fund_stratification_style",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_stratification_style_positions LIKE portfolio.members_fund_stratification_style_positions",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_fund_trades LIKE portfolio.members_fund_trades",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_profile LIKE portfolio.members_profile",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_profile_articles LIKE portfolio.members_profile_articles",
	"CREATE TABLE IF NOT EXISTS mtr_marketaco.members_settings LIKE portfolio.members_settings"
);

for ($n = 0; $n < sizeof($aQueries); $n++){

	$query = $aQueries[$n];
	try {
		$rsCreate = $mLink->prepare($query);
		$rsCreate->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

// Truncate tables (harmless if they were just created)
$aQueries = array(
	"TRUNCATE mtr_marketaco.members",
	"TRUNCATE mtr_marketaco.members_fund",
	"TRUNCATE mtr_marketaco.members_fund_aggregate",
	"TRUNCATE mtr_marketaco.members_fund_aggregate_history",
	"TRUNCATE mtr_marketaco.members_fund_composite",
	"TRUNCATE mtr_marketaco.members_fund_liveprice",
	"TRUNCATE mtr_marketaco.members_fund_month_to_month",
	"TRUNCATE mtr_marketaco.members_fund_positions",
	"TRUNCATE mtr_marketaco.members_fund_positions_details",
	"TRUNCATE mtr_marketaco.members_fund_pricing",
	"TRUNCATE mtr_marketaco.members_fund_settings",
	"TRUNCATE mtr_marketaco.members_fund_stratification_basic",
	"TRUNCATE mtr_marketaco.members_fund_stratification_sector",
	"TRUNCATE mtr_marketaco.members_fund_stratification_sector_positions",
	"TRUNCATE mtr_marketaco.members_fund_stratification_style",
	"TRUNCATE mtr_marketaco.members_fund_stratification_style_positions",
	"TRUNCATE mtr_marketaco.members_fund_trades",
	"TRUNCATE mtr_marketaco.members_profile",
	"TRUNCATE mtr_marketaco.members_profile_articles",
	"TRUNCATE mtr_marketaco.members_settings"
);

for ($n = 0; $n < sizeof($aQueries); $n++){

	$query = $aQueries[$n];
	try {
		$rsCreate = $mLink->prepare($query);
		$rsCreate->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

// Now step through all the managers' member IDs and import their data
while($manager = $rsManagers->fetch(PDO::FETCH_ASSOC)){

	$aQueries = array(
		"INSERT INTO mtr_marketaco.members SELECT * FROM portfolio.members WHERE member_id = ",
		"INSERT INTO mtr_marketaco.members_fund SELECT * FROM portfolio.members_fund WHERE member_id = ",
		"INSERT INTO mtr_marketaco.members_fund_aggregate SELECT * FROM portfolio.members_fund_aggregate WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_aggregate_history SELECT * FROM portfolio.members_fund_aggregate_history WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_composite SELECT * FROM portfolio.members_fund_composite WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_liveprice SELECT * FROM portfolio.members_fund_liveprice WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_month_to_month SELECT * FROM portfolio.members_fund_month_to_month WHERE member_id = ",
		"INSERT INTO mtr_marketaco.members_fund_positions SELECT * FROM portfolio.members_fund_positions WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_positions_details SELECT * FROM portfolio.members_fund_positions_details WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_pricing SELECT * FROM portfolio.members_fund_pricing WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_settings SELECT * FROM portfolio.members_fund_settings WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_stratification_basic SELECT * FROM portfolio.members_fund_stratification_basic WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_stratification_sector SELECT * FROM portfolio.members_fund_stratification_sector WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_stratification_sector_positions SELECT * FROM portfolio.members_fund_stratification_sector_positions WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_stratification_style SELECT * FROM portfolio.members_fund_stratification_style WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_stratification_style_positions SELECT * FROM portfolio.members_fund_stratification_style_positions WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_fund_trades SELECT * FROM portfolio.members_fund_trades WHERE SUBSTRING_INDEX(fund_id, '-', 1) = ",
		"INSERT INTO mtr_marketaco.members_profile SELECT * FROM portfolio.members_profile WHERE member_id = ",
		"INSERT INTO mtr_marketaco.members_profile_articles SELECT * FROM portfolio.members_profile_articles WHERE member_id = ",
		"INSERT INTO mtr_marketaco.members_settings SELECT * FROM portfolio.members_settings WHERE member_id = "
	);

	for ($n = 0; $n < sizeof($aQueries); $n++){

		$query = $aQueries[$n]."'".$manager['member_id']."'";
//echo $query."\n";
		try {
			$rsCreate = $mLink->prepare($query);
			$rsCreate->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

	}

//break; // Debug - stop after one manager

}

echo "Done!\n";


?>