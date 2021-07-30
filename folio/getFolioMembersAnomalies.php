<?php
// This commandline batch script generates a report of all FOLIOfn members who have funds with ranking anomalies.
// *Note - this will not run within a web browser.
// Written by: Jeff Saunders 10/12/18
// Modified by: Jeff Saunders - 10/15/18 - Added membership level

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
$emailTitle = 'FOLIOfn Member\'s Funds with Portfolio Anomalies';

// Body
$message = '
<meta http-equiv="Content-Type" content="text/html; charset=Windows-1252">
<title></title>
<html>
<head>
	<style>
		table td {word-wrap:break-word;}
	</style>
</head>
<body>
<font size="+2"><strong>'.$emailTitle.'</strong></font><br>
';

// Get the anomalies for each fund held by a FOLIOfn member
$query = "
	SELECT *
	FROM rank_anomalies ra
	LEFT JOIN rank_notes rn USING (fund_id, as_of_date)
	WHERE ra.member_id IN (
		SELECT m.member_id
		FROM members m, members_subscriptions ms, site_products p
		WHERE ms.active = 1
		AND ms.folio = 1
		AND ms.product_id IN (2,3,4,10)
		AND p.product_id = ms.product_id
		AND m.member_id = ms.member_id
	)
	ORDER BY ra.member_id + 0 ASC, ra.fund_id ASC, ra.as_of_date ASC";
//echo $query;die();
try{
	$rsAnomalies = $mLink->prepare($query);
	$rsAnomalies->execute();
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
	<th>Fund ID</th>
	<th>Username</th>
	<th>Name</th>
	<th>Email</th>
	<th>Phone</th>
	<th>Membership</th>
	<th>Flag Date</th>
	<th>Anomaly Date</th>
	<th>NAV</th>
	<th>Prev. NAV</th>
	<th>Change</th>
</tr>
';

$fundID = "";
while($anomaly = $rsAnomalies->fetch(PDO::FETCH_ASSOC)){

	// Look up member & fund information
	$query = "
		SELECT m.username, CONCAT(m.name_first, ' ', m.name_last) AS name, m.email, m.phone_day, mf.fund_symbol, mf.active, p.alt_product_name as membership
		FROM members m, members_fund mf, members_subscriptions ms, site_products p
		WHERE mf.fund_id = :fund_id
		AND mf.member_id = m.member_id
		AND ms.member_id = m.member_id
		AND ms.active = 1
		AND p.product_id = ms.product_id
	";
	//echo $query;die();
	try{
		$rsMember = $mLink->prepare($query);
		$aValues = array(
			':fund_id'	=> $anomaly["fund_id"]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";//die();
		$rsMember->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$member = $rsMember->fetch(PDO::FETCH_ASSOC);

	if ($member['active'] != '1'){
		continue;
	}

	if ($fundID != "" && $anomaly["fund_id"] != $fundID){

		// Print seperater if membership level changes
		$message .= '
<tr bgcolor="#CCCCCC">
	<th>Member ID</th>
	<th>Fund ID</th>
	<th>Username</th>
	<th>Name</th>
	<th>Email</th>
	<th>Phone</th>
	<th>Membership</th>
	<th>Flag Date</th>
	<th>Anomaly Date</th>
	<th>NAV</th>
	<th>Prev. NAV</th>
	<th>Change</th>
</tr>
		';

	}
	$fundID = $anomaly["fund_id"];

	// Anomaly row
	$message .= '
<tr>
	<td align="right">'.trim($anomaly["member_id"]).'</td>
	<td>'.trim($anomaly["fund_id"]).' ('.trim($member["fund_symbol"]).')</td>
	<td>'.trim($member["username"]).'</td>
	<td>'.trim($member["name"]).'</td>
	<td>'.trim($member["email"]).'</td>
	<td>'.trim($member["phone_day"]).'</td>
	<td>'.trim($member["membership"]).'</td>
	<td>'.date('m/d/Y', strtotime(trim($anomaly["as_of_date"]))).'</td>
	<td>'.date('m/d/Y', strtotime(trim($anomaly["anomaly_date"]))).'</td>
	<td align="right">'.$anomaly["price"].'</td>
	<td align="right">'.$anomaly["prev_price"].'</td>
	<td align="right">'.$anomaly["percent_change"].'%</td>
</tr>
	';

	if ($anomaly['note'] != ""){

		// Look up who entered the note
		$query = "
			SELECT CONCAT(name_first, ' ', name_last) AS name
			FROM members
			WHERE member_id = :member_id
		";
		//echo $query;die();
		try{
			$rsAdmin = $mLink->prepare($query);
			$aValues = array(
				':member_id'	=> $anomaly["created_by"]
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery."\n";//die();
			$rsAdmin->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$admin = $rsAdmin->fetch(PDO::FETCH_ASSOC);

		$message .= '
<tr>
	<td colspan="12">
<em>Note for above entered by '.$admin['name'].' on '.date('m/d/Y @ h:i:sa T', $anomaly['note_timestamp']).'</em><br>
<pre>'.$anomaly['note'].'</pre><br>
	</td>
</tr>
		';

	}

}

//echo $message;die();

// Footer
$message .= '
<tr>
	<td colspan="12" bgcolor="#000000"><br></td>
</tr>
</table>
';

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
$email->Subject   = 'FOLIOfn Member\'s Funds with Portfolio Anomalies as of '.date("m/j/y");
$email->Body      = $message;
$email->AddAddress('daniel.miroballi@mcm.marketocracy.com');
if ($sendToKen){
	$email->AddCC('ken.kam@marketocracy.com');
}
$email->AddCC('jeff.saunders@marketocracy.com');
$email->IsHTML(true);
$email->Send();

?>
