<?php
/*
The purpose of this script is to populate the rank_raw_nav table with the raw NAV data extracted via /Marketocracy/Scripts/build/bin/getPriceHistoryForFundOnDate.py script on stocks1.
*Note - this will not run within a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Make room for daddy
ini_set('memory_limit', '1024M');

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Test passed values
if (!isset($_REQUEST['input'])){
	echo "You must specify an input file (e.g. importRawNAVHistory.php \"input=filename.csv\")\n";
	die();
}

if (!file_exists($_REQUEST['input'])){
	echo $_REQUEST['input']." NOT FOUND!\n";
	die();
}

// Function to read a CSV file in as an associative array, assigning the values of line 1 as key values.
function csv2array($filename, $delimiter=','){

	// read the CSV lines into a numerically indexed array
	$all_lines = @file( $filename );
	if (!$all_lines){
		return FALSE;
	}
	$csv = array_map(function(&$line) use ($delimiter){
		return str_getcsv($line, $delimiter);
	}, $all_lines);

	// use the first row's values as keys for all other rows
	array_walk($csv, function(&$a) use ($csv){
		$a = array_combine($csv[0], $a);
	});

	// remove column header row
	array_shift($csv);

	return $csv;
}

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Assign passed values
$input = $_REQUEST['input'];

//Open the source spreadsheet assigning the data to an array
//$aNAVs= array_map('str_getcsv', file('/root/MDS_NAV_output_20170228.csv'));
//$aNAVs= csv2array('/root/MDS_NAV_output_20170228.csv');
$aNAVs= csv2array($input);

//print_r($aNAVs);die();

// Step through them and insert a row for each
$inserted = 0;

foreach($aNAVs as $key=>$aNAV){
//print_r($aNAV);//die();
//echo date('Ymd', strtotime($aNAV['AsOfDate']))."\n";//die();
//echo strtotime($aNAV['AsOfDate'])."\n";die();

	// Make sure this fund even existed on the run date, skip it if it didn't
 	if ($aNAV['NAV'] == ""){  // All existing funds would have a current NAV
		continue;
	}

	// Get the Nova member_id, if they have one
	$query = "
		SELECT member_id
		FROM members
		WHERE fb_primarykey  = 'X\'".$aNAV['ManagerKey']."\''
	";
	try{
		$rsMemberID = $mLink->prepare($query);
		$rsMemberID->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$memberID = $rsMemberID->fetch(PDO::FETCH_ASSOC);
	$member_id = $memberID['member_id'];

	// If we don;t have this member in Nova, then just skip them (should NEVER happen since the source list was Nova generated).
	if ($member_id == ""){
		continue;
	}

	// Ok, they exist - Get the Nova fund_id
	$query = "
		SELECT fund_id
		FROM members_fund
		WHERE fb_primarykey  = 'X\'".$aNAV['FundKey']."\''
	";
	try{
		$rsFundID = $mLink->prepare($query);
		$rsFundID->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$fundID = $rsFundID->fetch(PDO::FETCH_ASSOC);
	$fund_id = $fundID['fund_id'];

	// Insert raw NAV record
	$query = "
		INSERT INTO rank_raw_nav (
			fundkey,
			fund_id,
			managerkey,
			member_id,
			as_of_date,
			as_of_timestamp,
			nav,
			nav_1,
			nav_3,
			nav_5,
			nav_10,
			nav_15,
			nav_me,
			nav_qe,
			nav_ye
		)VALUES(
			:fundkey,
			:fund_id,
			:managerkey,
			:member_id,
			:as_of_date,
			:as_of_timestamp,
			:nav,
			:nav_1,
			:nav_3,
			:nav_5,
			:nav_10,
			:nav_15,
			:nav_me,
			:nav_qe,
			:nav_ye
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':fundkey'			=> "X'".$aNAV['FundKey']."'",
			':fund_id'			=> ($fund_id != "" ? $fund_id : NULL),
			':managerkey'		=> "X'".$aNAV['ManagerKey']."'",
			':member_id'		=> ($member_id != "" ? $member_id : NULL),
			':as_of_date'		=> date('Ymd', strtotime($aNAV['AsOfDate'])),
			':as_of_timestamp'	=> strtotime($aNAV['AsOfDate']),
			':nav'				=> ($aNAV['NAV'] != "" ? $aNAV['NAV'] : NULL),
			':nav_1'			=> ($aNAV['NAV_1'] != "" ? $aNAV['NAV_1'] : NULL),
			':nav_3'			=> ($aNAV['NAV_3'] != "" ? $aNAV['NAV_3'] : NULL),
			':nav_5'			=> ($aNAV['NAV_5'] != "" ? $aNAV['NAV_5'] : NULL),
			':nav_10'			=> ($aNAV['NAV_10'] != "" ? $aNAV['NAV_10'] : NULL),
			':nav_15'			=> ($aNAV['NAV_15'] != "" ? $aNAV['NAV_15'] : NULL),
			':nav_me'			=> ($aNAV['NAV_ME'] != "" ? $aNAV['NAV_ME'] : NULL),
			':nav_qe'			=> ($aNAV['NAV_QE'] != "" ? $aNAV['NAV_QE'] : NULL),
			':nav_ye'			=> ($aNAV['NAV_YE'] != "" ? $aNAV['NAV_YE'] : NULL)
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";die();
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$inserted++;
//die();
//break;
}

echo "\nDone!\n";
echo number_format($inserted)." of ".number_format(count($aNAVs))." NAV Records Inserted.\n\n";

?>
