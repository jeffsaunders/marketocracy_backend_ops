<?php
// This script corrects invalid "next_bill_timestamp" values in member's subscription records.
// On occasion the checkout process in Portfolio fails to pass a valid timestamp for when the first recurring bill is due so a timestamp of "0" is written, which equates to 1/1/1970.
// Since billing is designed to bill all subscriptions whose billing date is before "now" it bills them again the very next day.
// Making it worse, it adds the correct amount of time to that value to store the NEXT date to bill (e.g. it adds a month) resulting in the timestamp being changed to 2/1/1970, so it bills AGAIN the next night.  Rinse and repeat!!
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

// Set today's date in YYYYMMDD format
$today = date('Ymd');
//$today = 20170202;

// Exclude the following product IDs (free/not billed)
$excludeList = "0,1,5,10,11,99";

// Get all the subscriptions that are due to be billed today
$query = "
	SELECT *
	FROM ".$subscriptions_table."
	WHERE active = 1
	AND product_id NOT IN (:excludeList)
	AND FROM_UNIXTIME(next_bill_timestamp, '%Y%m%d') + 0 <= :today
	ORDER BY member_id
";
try {
	$rsSubs = $mLink->prepare($query);
	$aValues = array(
		':excludeList'	=> $excludeList,
		':today'		=> $today
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;//die();
	$rsSubs->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
dump_rs($rsSubs); // Display result set - debug






/*

// Get info for all negative positions in active funds
$lastLogin = strtotime("-1 month"); // Ignore anyone who hasn't logged in recently
$query = "
	SELECT 	p.fund_id,
			p.stockSymbol,
			p.totalShares,
			f.active as fund_active,
			f.fund_symbol,
			f.member_id,
			f.fb_primarykey AS fundKey,
			f.composite_fund,
			m.active AS member_active,
			m.username,
			m.fb_primarykey as managerKey
	FROM members_fund_stratification_basic p, members_fund f, members m
	WHERE p.totalShares < 0
	AND f.active = 1
	AND f.short_fund <> 1
	AND f.fund_id = p.fund_id
	AND m.member_id = f.member_id
	AND m.last_login > ".$lastLogin."
	ORDER BY m.member_id + 0 ASC
";
//echo $query;die();

/*
Add this under the WHERE clause above to restrict this to only active members (flagged as "active").  Not likely needed as they probably haven't logged in for over a month anyway.
	AND m.active = 1
/

try{
	$rsShortPositions = $mLink->prepare($query);
	$rsShortPositions->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	$aErrors[] = $error;
}

// Let's start outputting
// Set up the page
echo '
<head>
	<title>Position Lookup Tool</title>
</head>
';

echo '
<h2>The following is a list of all short positions in active members\' funds as of '.date('m/d/y @ h:i A (T)').':</h2>
';

// Let's get loopin'
$emptyReport = true;
$shortsCnt = 0;
// Loop through all the results
while($shorts = $rsShortPositions->fetch(PDO::FETCH_ASSOC)){

	// First loop only
	if ($shortsCnt == 0){
		// Write header row
		echo '
<table border="1" cellpadding="5" cellspacing="0">
	<tr style="background-color:#909090;">
		<th style="padding:5px 10px;">Member ID</th>
		<th style="padding:5px 10px;">Username</th>
		<th style="padding:5px 10px;">Manager Key</th>
		<th style="padding:5px 10px;">Fund ID</th>
		<th style="padding:5px 10px;">Fund Symbol</th>
		<th style="padding:5px 10px;">Fund Key</th>
		<th style="padding:5px 10px;">Stock Symbol</th>
		<th style="padding:5px 10px;">Total Shares</th>
		<th style="padding:5px 10px;">Note</th>
	</tr>
		';
	}

	// Assign the values to variables
	$memberID 	= trim($shorts['member_id']);
	$username	= trim($shorts['username']);
	$mgrKey  	= trim($shorts['managerKey']);
	$fundID		= trim($shorts['fund_id']);
	$fundSymbol	= trim($shorts['fund_symbol']);
	$fundKey 	= trim($shorts['fundKey']);
	$stockSymbol= trim($shorts['stockSymbol']);
	$shares		= trim($shorts['totalShares']);

	// Build "Note" string
	$note = " ";
	if ($shorts['member_active'] == 0){
		$note = "Inactive Member";
	}elseif ($shorts['fund_active'] == 0){
		$note = "Inactive Fund";
	}elseif ($shorts['composite_fund'] == 1){
		$note = "GIPS Composite Fund";
	}

	// Write the row
	echo '
	<tr>
		<td align="right" style="padding:5px 10px;">'.$memberID.'</td>
		<td style="padding:5px 10px;">'.$username.'</td>
		<td style="padding:5px 10px;">'.$mgrKey.'</td>
		<td align=right style="padding:5px 10px;">'.$fundID.'</td>
		<td align="center">'.$fundSymbol.'</td>
		<td style="padding:5px 10px;">'.$fundKey.'</td>
		<td align="center">'.$stockSymbol.'</td>
		<td align=right style="padding:5px 10px;">'.$shares.'</td>
		<td style="padding:5px 10px;">'.$note.'</td>
	</tr>
	';

	$emptyReport = false;
	$shortsCnt++;

}

if ($emptyReport){
	echo '<strong>No short positions found.</strong>';
}else{
	echo'
</table>
<h2>'.$shortsCnt.' short position'.($shortsCnt > 1 ? 's' : '').' found.</h2><br>
	';
}
*/
?>
