<?php
// This script pulls periodic NAVs for all active funds for ranking purposes.
// It replaces the same-named Python script that used to pull the same data from Legacy FrontBase
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 4/11/18
// Modified by: Jeff Saunders - 11/2/18 - Pulling data from both folio and portfolio tables, folio records supersede portfolio records, also writing directly to rank_raw_nav table (no extract file)
// MOdified by: Jeff Saunders - 7/7/20 - Added code to check if periodical historical NAV date is a trading day, if not roll back to last trading day before it.

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
//session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Load some useful functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctionsPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

//Determine dates to process
$sDate = date('Ymd', strtotime('yesterday'));
if (isset($_REQUEST['date'])){
	$sDate = date('Ymd', strtotime($_REQUEST['date']));
}
$pDate = date('n/j/Y', strtotime($sDate));

// X years ago
$sDate_x = date('Ymd', strtotime($sDate . " -1 year"));
// Now make sure it's a valid trading day - fix it if not.
$sDate_1 = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));
$sDate_x = date('Ymd', strtotime($sDate . " -3 years"));
$sDate_3 = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));
$sDate_x = date('Ymd', strtotime($sDate . " -5 years"));
$sDate_5 = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));
$sDate_x = date('Ymd', strtotime($sDate . " -10 years"));
$sDate_10 = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));
$sDate_x = date('Ymd', strtotime($sDate . " -15 years"));
$sDate_15 = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));

// This month's end
// GET THE LAST TRADING DAY OF THE MONTH AND MAKE THAT'S THE VALUE OF $sDate_NOW
// MAY NEED THIS FOR ALL DATES TO MAKE SURE THEY ARE EACH TRADING DAYS...just do "this" month, for now
$sDate_NOW = date('Ymd', checkForMarketDate(strtotime($sDate) + 43200, $mLink));

// Previous month's end
$sDate_x = date('Ymd', strtotime("last day of previous month", strtotime($sDate)));
$sDate_ME = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));

# Previous quarter's end (simple month comparison method)
if (date("n", strtotime($sDate)) < 4) {
	$sDate_x = date('Ymd', strtotime('last day of december', strtotime($sDate . " -1 year")));
}elseif (date("n", strtotime($sDate)) < 7) {
	$sDate_x = date('Ymd', strtotime('last day of march', strtotime($sDate)));
}elseif (date("n", strtotime($sDate)) < 10) {
	$sDate_x = date('Ymd', strtotime('last day of june', strtotime($sDate)));
}else {
	$sDate_x = date('Ymd', strtotime('last day of september', strtotime($sDate)));
}
// Now make sure it's a valid trading day - fix it if not.
$sDate_QE = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));

// Previous year's end
$sDate_x = date('Ymd', strtotime('last day of december', strtotime($sDate . " -1 year")));
$sDate_YE = date('Ymd', checkForMarketDate(strtotime($sDate_x) + 43200, $mLink));

// Build an array of the applicable dates
$aDates = array("NAV"=>$sDate_NOW,"NAV_1"=>$sDate_1,"NAV_3"=>$sDate_3,"NAV_5"=>$sDate_5,"NAV_10"=>$sDate_10,"NAV_15"=>$sDate_15,"NAV_ME"=>$sDate_ME,"NAV_QE"=>$sDate_QE,"NAV_YE"=>$sDate_YE);
//print_r($aDates);

// Build a string from that array for use in subsequent queries
$sDates = "'" . implode("','", $aDates) . "'";




//echo $sDates;die();





// Create the output files
//$filename = "NAV_history_output_".$sDate.".csv";
//$fp = fopen("/var/www/html/folio/tmp/".$filename, "w");

// Write header rows
//$headers = "FundID,MemberID,AsOfDate,NAV,NAV_1,NAV_3,NAV_5,NAV_10,NAV_15,NAV_ME,NAV_QE,NAV_YE\r\n";
//fwrite($fp, $headers);

// Get the active (folio) fund IDs
$query = "
	SELECT mf.fund_id
	FROM members_fund mf, members_subscriptions ms
	WHERE mf.folio_cutover IS NOT NULL
	AND ms.active = 1
	AND ms.folio = 1
	AND mf.member_id = ms.member_id
	ORDER BY mf.member_id + 0 ASC, mf.fund_id ASC
";
// That will pull in all members who are active on FOLIOfn
// Add "AND ms.product_id IN (comma delimited levels)" to specify specific membership levels
// ...or just = for one, e.g. "AND ms.product_id = 10"
try {
	$rsFundIDs = $mLink->prepare($query);
	$rsFundIDs->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Delete any old rows for this period - we are replacing them
$query = "
        DELETE FROM rank_raw_nav
        WHERE as_of_date = :as_of_date
";
try{
        $rsDelete = $mLink->prepare($query);
        $aValues = array(
                ':as_of_date' => date('Ymd', strtotime($pDate)),
        );
        $preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
        //echo $preparedQuery."\n";die();
        $rsDelete->execute($aValues);
}
catch(PDOException $error){
        // Log any error
        file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through the funds
while($fund = $rsFundIDs->fetch(PDO::FETCH_ASSOC)){

	// Assign some values
	$fundID = $fund['fund_id'];
	$memberID = explode("-", $fundID)[0];

	// Get the funds NAVs on the specified dates from Portfolio
//	$query = "
//		SELECT date, (totalValue/shares) AS nav
//		FROM members_fund_pricing
//		WHERE fund_id = :fund
//		AND date IN (:dates)
//	";
	// For some reason PDO substitution seems to break here, so reverting to inline substitution
	$query = "
		SELECT date, (totalValue/shares) AS nav
		FROM members_fund_pricing
		WHERE fund_id = '".$fundID."'
		AND date IN (".$sDates.")
	";
	try {
		$rsPNAVs = $mLink->prepare($query);
//		$aValues = array(
//			':fund' 	=> $fundID,
//			':dates' 	=> $sDates
//		);
//		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
//		$rsPNAVs->execute($aValues);
		$rsPNAVs->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
//dump_rs($rsPNAVs);

	// Build an array of the NAVs, keyed by date
	$aNAVs = array();
	while($row = $rsPNAVs->fetch(PDO::FETCH_ASSOC)){
		$aNAVs += [$row['date'] => $row['nav']];
	}
//echo $fundID."\n";
//print_r($aNAVs);

	// Now do it again using the Folio data
	$query = "
		SELECT date, (totalValue/shares) AS nav
		FROM folio_fund_pricing
		WHERE fund_id = '".$fundID."'
		AND date IN (".$sDates.")
	";
	try {
		$rsFNAVs = $tLink->prepare($query);
		$rsFNAVs->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Add to the NAVs array
	while($row = $rsFNAVs->fetch(PDO::FETCH_ASSOC)){

		// Toss stale values from Portfolio (if exist)
		unset($aNAVs[$row['date']]);

		$aNAVs += [$row['date'] => $row['nav']];
	}


//print_r($aNAVs);die();

// step through $aDates array, printing the value then comma for each element
//headers = "FundKey,ManagerKey,AsOfDate,NAV,NAV_1,NAV_3,NAV_5,NAV_10,NAV_15,NAV_ME,NAV_QE,NAV_YE\r\n"
//	$sRow = $fundID . "," . $memberID . "," . $pDate . ",";
	$aNAV = array();
	foreach($aDates as $key=>$date){
//		$sRow .= $aNAVs[$date] . ",";
		$aNAV += [$key => $aNAVs[$date]];  // Build array for SQL insert instead
	}

	// Pop the trailing comma off
//	$sRow = substr($sRow, 0, -1);

	// Add CRLF
//	$sRow .= "\r\n";
//echo $sRow;
	// write that line to the file
//	fwrite($fp, $sRow);

//echo $sRow;die();


	if ($aNAV['NAV'] != ""){

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
			:1_nav,
			:3_nav,
			:5_nav,
			:10_nav,
			:15_nav,
			:me_nav,
			:qe_nav,
			:ye_nav
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':fundkey'			=> NULL,
			':fund_id'			=> $fundID,
			':managerkey'		=> NULL,
			':member_id'		=> $memberID,
			':as_of_date'		=> date('Ymd', strtotime($pDate)),
			':as_of_timestamp'	=> strtotime($pDate),
			':nav'				=> ($aNAV['NAV'] != "" ? $aNAV['NAV'] : NULL),
			':1_nav'			=> ($aNAV['NAV_1'] != "" ? $aNAV['NAV_1'] : NULL),
			':3_nav'			=> ($aNAV['NAV_3'] != "" ? $aNAV['NAV_3'] : NULL),
			':5_nav'			=> ($aNAV['NAV_5'] != "" ? $aNAV['NAV_5'] : NULL),
			':10_nav'			=> ($aNAV['NAV_10'] != "" ? $aNAV['NAV_10'] : NULL),
			':15_nav'			=> ($aNAV['NAV_15'] != "" ? $aNAV['NAV_15'] : NULL),
			':me_nav'			=> ($aNAV['NAV_ME'] != "" ? $aNAV['NAV_ME'] : NULL),
			':qe_nav'			=> ($aNAV['NAV_QE'] != "" ? $aNAV['NAV_QE'] : NULL),
			':ye_nav'			=> ($aNAV['NAV_YE'] != "" ? $aNAV['NAV_YE'] : NULL)
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";die();
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	}
	// Jump back and do it again!

}

// Close 'er up
//fclose($fp);

echo "NAV's Calculated.  You may now proceed to run the Ranking's process.\n";

?>
