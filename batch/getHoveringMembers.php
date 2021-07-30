<?php
/*
The purpose of this script is build email lists of all the members who have yet to enter their trial (hovering), split by those with one fund and those with more than one.
*Note - this will not run within a web browser.
*/

// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Let's start outputting
$fp1 = fopen("/var/www/html/tmp/OneFund_".date("Ymd").".csv", "w");
$fp2 = fopen("/var/www/html/tmp/MoreThanOneFund_".date("Ymd").".csv", "w");

// Write header row
$headers = "email,name_first\r\n";
fwrite($fp1, $headers);
fwrite($fp2, $headers);

// Get all the "hovering" members who never logged in after trial started
	$query = "
		SELECT *
		FROM members_subscriptions s, members m
		WHERE s.start_timestamp IS NULL
		AND s.active = 1
		AND s.member_id = m.member_id
		AND s.product_id = 99
	";
try{
	$rsHovers = $mLink->prepare($query);
	$rsHovers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them and count how many funds they have
while ($hover = $rsHovers->fetch(PDO::FETCH_ASSOC)){

	$query = "
		SELECT COUNT(*) AS funds
		FROM members_fund
		WHERE member_id = ".$hover['member_id']."
		AND active = 1
	";
	try{
		$rsFunds = $mLink->prepare($query);
		//echo $query;//die();
		$rsFunds->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$funds = $rsFunds->fetch(PDO::FETCH_ASSOC);

	if ($funds['funds'] < 2){
		// Write the row
		$row = '"'.$hover['email'].'","'.$hover['name_first'].'"';
		$row .= "\r\n";
		fwrite($fp1, $row);
	}else{
		// Write the row
		$row = '"'.$hover['email'].'","'.$hover['name_first'].'"';
		$row .= "\r\n";
		fwrite($fp2, $row);
	}
//die();
}

// Close 'er up
fclose($fp1);
fclose($fp2);

echo "Done!\n";

?>