<?php
/*
The purpose of this script is to generate a periodic (annual) Texas sales tax report
*Note - this will not run within a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run long enough
//set_time_limit(900); // 15 minutes

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Get start date
// Go back 1 year and determine the first second of January 1
$start_year = date('Y', strtotime('1 year ago'));  // Starting year number
$start_timestamp = mktime(0, 0, 0, 1, 1, $start_year);  // Timestamp of starting second of last year
//echo $start_timestamp."\n";

// Get end date
// Determine the first second of this year's first day and subtract 1 second to get last second of last year
$end_year = date('Y', strtotime('now'));  // This year
$end_timestamp = mktime(0, 0, 0, 1, 1, $end_year) - 1;  // Timestamp of this year's first second, minus 1 second (results in timestamp of last year's last second)
//echo $end_timestamp."\n";

// Get all the cleared charges between the start and end timestamps
// PDO BUG!  All strings bound (:var) for a "BETWEEN" get quoted thus are treated as strings - not gonna work for integer timestamps
// Must use old syntax as a workaround
$query = "
	SELECT SUM(unitPrice) AS totalSales
	FROM ".$transaction_history_table."
	WHERE bill_timestamp BETWEEN ".$start_timestamp." AND ".$end_timestamp."
";
try {
	$rsSales = $mLink->prepare($query);
//	$aValues = array(
//		':table'	=> $transaction_history_table,
//		':start'	=> $start_timestamp,
//		':end'		=> $end_timestamp
//	);
	//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;//die();
//	$rsSales->execute($aValues);
	$rsSales->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$totalSales = $rsSales->fetchColumn();

// Now get all the TAXABLE charges between the start and end timestamps
$query = "
	SELECT SUM(unitPrice) AS taxableSales
	FROM ".$transaction_history_table."
	WHERE bill_timestamp BETWEEN ".$start_timestamp." AND ".$end_timestamp."
	AND taxable = 1
";
try {
	$rsTaxable = $mLink->prepare($query);
	$rsTaxable->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$taxableSales = $rsTaxable->fetchColumn();

// Calculate the tax collected between the start and end timestamps
$taxCollected = $taxableSales * .0825;

// Build report
//$to = 'someone@marketocracy.com';
//$to = 'jeff.saunders@marketocracy.com'; // Testing
$to = 'accounts.payable@marketocracy.com';

// Define the subject and title
$subject = 'Sales Tax Report for '.$start_year;

// Body
$message = '
<meta http-equiv="Content-Type" content="text/html; charset=Windows-1252">
<title></title>
<html>
<body>
<font size="+1"><strong>'.$subject.'</strong></font><br><br>
<table cellspacing="3" cellpadding="3" bgcolor="#000000" style="font-family:Arial, Helvetica, sans-serif; font-size:14px;">
<tr bgcolor="#CCCCCC">
	<th>&nbsp;Total Sales&nbsp;</th>
	<th>Taxable Sales</th>
	<th>Tax Collected</th>
</tr>
<tr bgcolor="#FFFFFF">
	<td style="text-align:right;">$'.number_format($totalSales, 2, '.', ',').'</td>
	<td style="text-align:right;">$'.number_format($taxableSales, 2, '.', ',').'</td>
	<td style="text-align:right;">$'.number_format($taxCollected, 2, '.', ',').'</td>
</tr>
<tr>
	<td colspan="3" style="max-height:10px;"></td>
</tr>
</table>
<br>
<strong>Tax Filing Website:</strong> <a href="https://comptroller.texas.gov">https://comptroller.texas.gov</a><br>
<strong>Direct Link to Sales Tax Login:</strong>  <a href="https://mycpa.cpa.state.tx.us/securitymp1portal/displayLoginUser.do">https://mycpa.cpa.state.tx.us/securitymp1portal/displayLoginUser.do</a>
</body>
</html>
';

//die($message);
// To send HTML mail, the Content-type header must be set
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

// Additional headers
$headers .= 'From: Marketocracy IT <it@marketocracy.com>' . "\r\n";
$headers .= 'Cc: jeff.saunders@marketocracy.com' . "\r\n";
//$headers .= 'Bcc: someone.else@marketocracy.com, someone.outside@otherdomain.com' . "\r\n"; // Example

// Mail it
mail($to, $subject, $message, $headers);

?>
