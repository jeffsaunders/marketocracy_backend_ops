<?php

// Define some global system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDOstock.php");

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");


// Start me up
session_start();

function execute_query($query)
{
        try{
                $rows = $stLink->prepare($query);
                $rows->execute();
        }
        catch(PDOException $error){
        // Log any error
                file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
        }
	
	return $rows;
}

function is_alias_current($alias_id=0) {

	$sa_rows = NULL;
	$sa_rows = execute_query("select current from stock_alias where uid = $alias_id");
	if ($sa_rows->rowCount() == 0){
		return 0;	
        }

	if ($sa_rows->rowCount() == 1){
		return $sa_rows[0]['current'];
        }

	return 0;
}


$options = getopt("k:i:t:");
var_dump($options);

//if (empty($options[t])) {
//	print "Usage: unapplyCA.php [-k <frontbase_key>] [-i <action_id>] ";
//    print "-t <type>\n";
//    print "Valid types: ACQUIS, BANCR, CHG_ID, CHG_LIST, CHG_NAME, CHG_TKR\n";
//	print "DELIST, DVD_CASH, DVD_STOCK, LIST, SPIN, STOCK_SPLIT\n";
//}
$where_clause = "where action_id = 'INVALID'";
$table_names = array("acquisition_ca", "bankruptcy_ca", "bonus_issue_ca", 
	"cash_dividend_ca", "cusip_change_ca", "distribution_ca", 
	"exchange_change_ca", "name_cahnge_ca", "new_listing_ca",
	"remove_listing_ca", "spinoff_ca", "stock_dividend_ca",
	"stock_split_ca", "symbol_change_ca");
if (!empty($options["k"])) {
	$where_clause = 'fb_aliaskey = ".$options["k"]."';
}
if (!empty($options["i"])) {
	$where_clause = 'action_id = ".$options["i"]."';
}

print "$where_clause \n";
$table = NULL;
$uid = NULL;
$alias_id = NULL;
foreach ($table_names as $t) {
	$query = " 
		SELECT uid, alias_id
	        FROM .$t 
       		WHERE .$where_clause. 
	";

	try{
		$ca_rows = $stLink->prepare($query);
		$ca_rows->execute();
	}
	catch(PDOException $error){
	// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
        }

	if ($ca_rows->rowCount() > 1){
		Exit("Too many rows returned for $t");
        }

	if ($ca_rows->rowCount() == 1){
		$table = $t;			
		$uid = $ca_rows[0]['uid'];
		$alias_id = $ca_rows[0]['alias_id'];
		$effective_date = $ca_rows[0]['effective_date'];
		break;
	}
}

if (is_null($table)) {
	Exit("CA not found\n");
} 
else {
	$stock_id = 0;
	// If this is the current (active) one
	// Set previous alias to current
	$sa_rows = execute_query("SELECT current, stock_id FROM stock_alias WHERE uid = .$alias_id.");
	if ($sa_rows->rowCount() == 1) {
		$row = $sa_row[0];
		$stock_id = $row['stock_id'];
		if ($row['current'] == 1) {
			$query = "
				UPDATE stock_alias
				SET current = 1
				WHERE uid in
				(SELECT max uid WHERE stock_id = .$stock_id. and current = 0)
			";
			try {
				$update = $stLink->prepare($query);
				$update->execute();		
			}
			catch(PDOException $error){
			        // Log any error
			        file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

		}

	}     		
        if ($t == "listing_ca" or $t == "cusip_change_ca" or $t == "exchange_change_ca" or $t == "new_listing_ca" or "remove_listing_ca"
		or $t = "symbol_change_ca") { 
	$query = "
		DELETE from stock_alias	
		WHERE uid = .$alias_id.
		";
	try {
		$delete = $stLink->prepare($query);
		$delete->execute();		
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	}
	$query = "
		DELETE from $t 
		WHERE $where_clause 
		";
	try {
		$delete = $stLink->prepare($query);
		$delete->execute();		
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Special cases for lists, spinoffs and acquisitions
	
	if ($t == "listing_ca") {
		$query = "
			DELETE from stock_company	
			WHERE uid = .$stock_id.
		";
		try {
			$delete = $stLink->prepare($query);
			$delete->execute();		
		}
		catch(PDOException $error){
		// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}


	}	

	if ($t == "spinoff_ca") {
		$sa_rows = execute_query("select spinoff_symbol, effective_date from spinoff_ca where $where_clause"); 
		if ($sa_rows->rowCount() == 1) {
			$spinoff_symbol = $sa_rows[0]['spinoff_symbol'];
			$effective_date = $sa_rows[0]['effective_date']; 

		}

//		$sa_rows = 
	}	

	if ($t == "acquisition_ca") {
	}	
}
?>

