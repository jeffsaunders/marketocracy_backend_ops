<?php
// This commandline batch script generates a report of all members who upgraded to Plus or Pro the previous day.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 9/18/18
// Modified by: Jeff Saunders - 9/25/18 - Changed to report all upgrades since 9/15/18.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Determine if this copy is to be emailed to Ken as well as Dan
$sendToKen = false;
if (isset($_REQUEST['sendToKen']) && strtoupper($_REQUEST['sendToKen']) == "YES"){
	$sendToKen = true;
}

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Start the report
//$emailTitle = 'Membership Upgrades for '.date("m/j/y", strtotime("yesterday"));
$emailTitle = 'Membership Upgrades Since 09/15/18';

// Body
$message = '
<meta http-equiv="Content-Type" content="text/html; charset=Windows-1252">
<title></title>
<html>
<body>
<font size="+2"><strong>'.$emailTitle.'</strong></font><br>
';

// Define the first and last seconds of "yesterday"
//$startTimestamp = strtotime('yesterday');
$startTimestamp = 1536984000;  // 09/15/2018
$endTimestamp = strtotime('today') - 1; // Yesterday at 11:59:59 pm ET

//$startTimestamp = 1514782800;  // Testing 01/01/18
//echo $startTimestamp."|";echo $endTimestamp;die();

// See if anyone upgraded
$query = "
	SELECT count(m.member_id)
	FROM members m, members_subscriptions ms, site_products p
	WHERE ms.start_timestamp >= ".$startTimestamp."
	AND ms.start_timestamp < ".$endTimestamp."
	AND ms.product_id IN (2,3)
	AND ms.active = 1
	AND m.member_id = ms.member_id
	AND p.product_id = ms.product_id
	ORDER BY ms.start_timestamp ASC
";
//echo $query;die();
try{
	$rsCount = $mLink->prepare($query);
	$rsCount->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$upgrades = $rsCount->fetchColumn();

if ($upgrades == 0){

	// No upgrades to report
	$message .= '
<br><font size="+2">No Upgrades to Report.</font>
	';

}else{

	// Get the members who've upgraded
	$query = "
		SELECT m.member_id, m.username, m.name_first, m.name_last, m.email, m.phone_day, from_unixtime(ms.start_timestamp, '%m/%d/%Y@%h:%i:%s') as time, ms.bill_frequency, p.product_name
		FROM members m, members_subscriptions ms, site_products p
		WHERE ms.start_timestamp >= ".$startTimestamp."
		AND ms.start_timestamp < ".$endTimestamp."
		AND ms.product_id IN (2,3)
		AND ms.active = 1
		AND m.member_id = ms.member_id
		AND p.product_id = ms.product_id
		ORDER BY ms.start_timestamp ASC
	";
	//echo $query;die();
	try{
		$rsUpgrades = $mLink->prepare($query);
		$rsUpgrades->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Start email table
	$message .= '
<table cellspacing="0" cellpadding="3" border="1" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; border:1px solid #000000;">
	';

	// Header
	$message .= '
<tr bgcolor="#CCCCCC">
	<th>Member ID</th>
	<th>Username</th>
	<th>Name</th>
	<th>Email</th>
	<th>Phone</th>
	<th>Timestamp</th>
	<th>Upgraded To</th>
	<th>Billing</th>
</tr>
	';

	while($member = $rsUpgrades->fetch(PDO::FETCH_ASSOC)){

		// One row per member
		$message .= '
<tr>
	<td>'.trim($member["member_id"]).'</td>
	<td>'.trim($member["username"]).'</td>
	<td>'.trim($member["name_first"]).' '.trim($member["name_last"]).'</td>
	<td>'.trim($member["email"]).'</td>
	<td>'.trim($member["phone_day"]).'</td>
	<td>'.trim($member["time"]).'</td>
	<td>'.trim($member["product_name"]).' Membership</td>
	<td>'.trim($member["bill_frequency"]).'</td>
</tr>
		';

	}

	// Footer
	$message .= '
<tr>
	<td colspan="9" bgcolor="#000000"><br></td>
</tr>
</table>
	';

}

// Close the body
$message .= '
</body>
</html>
';

//die($message);

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Membership Upgrades through '.date("m/j/y", strtotime("yesterday"));
$email->Body      = $message;
$email->AddAddress('daniel.miroballi@mcm.marketocracy.com');
if ($sendToKen){
	$email->AddCC('ken.kam@marketocracy.com');
}
$email->AddCC('jeff.saunders@marketocracy.com');
$email->IsHTML(true);
$email->Send();

?>
