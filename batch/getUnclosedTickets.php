<?php
// This commandline batch script generates a report of any remaining open trade tickets as part of the nightly processing.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfig.php");

// Load some useful functions
//require("../includes/systemFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Check for open tickets
$query = "
	SELECT 	t.*,
			m.name_first,
			m.name_last,
			m.username,
			f.fund_symbol
	FROM ".$fund_tickets_table." t
	LEFT JOIN ".$members_table." m ON t.member_id = m.member_id
	LEFT JOIN ".$fund_table." f ON t.fund_id = f.fund_id
	WHERE (t.status = 'pending'
		   OR t.status = 'open'
	)
	AND t.cancel_status = 0
	ORDER BY t.type, t.openned ASC
";
//echo $query;die();
$rs_tickets = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Build report
//	$to = 'someone@marketocracy.com';
$to = 'jeff.saunders@marketocracy.com';

// Define the subject and title
$subject = 'Open ECN Tickets - '.date("m/j/y", strtotime("yesterday"));
$emailTitle = 'Open ECN Tickets - '.date("m/j/y", strtotime("yesterday"));

// Body
$message = '
<meta http-equiv="Content-Type" content="text/html; charset=Windows-1252">
<title></title>
<html>
<body>
<font size="+2"><strong>'.$emailTitle.'</strong></font><br>
';
if (mysql_num_rows($rs_tickets) == 0){
	$message .= '
<br><font size="+2">No open tickets.</font>
';
}else{
	$message .= '
<table cellspacing="0" cellpadding="3" border="1" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; border:1px solid #000000;">
	';
	$type = "Day";
	$Day = 0;
	$GTC = 0;
	while ($ticket = mysql_fetch_assoc($rs_tickets)){
		if ($ticket["type"] != $type){
			$message .= '
<tr>
	<td colspan="8" bgcolor="#880000"><br><br></td>
</tr>
			';
			$type = $ticket["type"];
		}
		$message .= '
<tr>
	<td colspan="8" bgcolor="#000000"><br></td>
</tr>
<tr>
	<td colspan="8">
			<strong>Member#</strong> '.$ticket["member_id"].' |&nbsp;&nbsp;<strong>Name:</strong> '.$ticket["name_first"].' '.$ticket["name_last"].' ('.$ticket["username"].') |&nbsp;&nbsp;<strong>Ticket#</strong> '.$ticket["ticket_key"].'
	</td>
</tr>
<tr bgcolor="#CCCCCC">
	<th>Fund ID</th>
	<th>Fund</th>
	<th>Submitted</th>
	<th>Action</th>
	<th>Type</th>
	<th>Symbol</th>
	<th>Shares</th>
	<th>Status</th>
</tr>
<tr>
	<td>'.$ticket["fund_id"].'</td>
	<td>'.$ticket["fund_symbol"].'</td>
	<td>'.date("m/j/y @ h:i:s T", $ticket["openned"]).'</td>
	<td>'.$ticket["action"].'</td>
	<td>'.$ticket["type"].'</td>
	<td>'.$ticket["symbol"].'</td>
	<td>'.$ticket["shares"].'</td>
	<td>'.$ticket["status"].'</td>
</tr>
		';

		$$ticket["type"]++;

	}
	$message .= '
<tr>
	<td colspan="8" bgcolor="#880000"><br><br></td>
</tr>
<!--<tr>
	<td colspan="8" bgcolor="#000000"><br></td>
</tr>-->
<tr>
	<td colspan="8"><font size="+1"><strong>Day Tickets:</strong> '.$Day.'&nbsp;&nbsp;&nbsp;<strong>GTC Tickets:</strong> '.$GTC.'&nbsp;&nbsp;&nbsp;<strong>Total Tickets:</strong> '.($Day+$GTC).'</font></td>
</tr>
</table>
</body>
</html>
	';
}
//die($message);
// To send HTML mail, the Content-type header must be set
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

// Additional headers
$headers .= 'From: Marketocracy IT <it@marketocracy.com>' . "\r\n";
$headers .= 'Cc: marty.fukui@marketocracy.com' . "\r\n";
//$headers .= 'Bcc: someone.else@marketocracy.com, someone.outside@otherdomain.com' . "\r\n"; // Example

// Mail it
mail($to, $subject, $message, $headers);

?>
