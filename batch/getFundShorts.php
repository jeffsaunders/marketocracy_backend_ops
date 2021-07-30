<?php
// This commandline batch script generates a report of any fund shorts (funds with negative positions) and emails it.
// A sister script, run beforehand, attempts to correct any erroneous short positions ("Display Shorts") so only true shorts should appear on this report.
// *Note - this will not run within a web browser.

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

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

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
*/

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
$fp = fopen("/var/www/html/tmp/ShortPositions_".date("Ymd").".csv", "w");

// Write header row
$headers = "MemberID,Username,ManagerKey,FundID,FundSymbol,FundKey,StockSymbol,TotalShares,Note\r\n";
fwrite($fp, $headers);

// Let's get loopin'
$emptyReport = true;

// Loop through all the results
while($shorts = $rsShortPositions->fetch(PDO::FETCH_ASSOC)){

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
	$note = "";
	if ($shorts['member_active'] == 0){
		$note = "Inactive Member";
	}elseif ($shorts['fund_active'] == 0){
		$note = "Inactive Fund";
	}elseif ($shorts['composite_fund'] == 1){
		$note = "GIPS Composite Fund";
	}

	// Write the row
	$row = '"'.$memberID.'","'.$username.'","'.$mgrKey.'","'.$fundID.'","'.$fundSymbol.'","'.$fundKey.'","'.$stockSymbol.'","'.$shares.'","'.$note.'"';
	$row .= "\r\n";
	fwrite($fp, $row);

	$emptyReport = false;
}

// Close 'er up
fclose($fp);

$emailBody = "The attached report is a list of all 'short' positions in members' funds.";

if ($emptyReport){
	$emailBody = "No short positions found today.";
}

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Short Positions Report';
$email->Body      = $emailBody;
//$email->AddAddress('ops-actions@marketocracy.com');
$email->AddAddress('marty.fukui@marketocracy.com');
$email->AddBCC('jeff.saunders@marketocracy.com');
if (!$emptyReport){
	$email->AddAttachment('/var/www/html/tmp/ShortPositions_'.date("Ymd").'.csv');
}
$email->Send();

// Delete report
unlink('/var/www/html/tmp/ShortPositions_'.date("Ymd").'.csv');

?>
