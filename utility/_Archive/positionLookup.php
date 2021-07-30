<?php
/*
The purpose of this script is to look up members/fund who hold a particular stock by passed stock symbol.
*Note - this must be run in a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run long enough
set_time_limit(900); // 15 minutes

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Make sure they passed at least one symbol
if (!isset($_REQUEST['symbol']) || $_REQUEST['symbol'] == ""){
	echo '
You must specify at least one stock symbol to look up (i.e. positionLookup.php?symbol=AAPL or positionLookup.php?symbol=AAPL,IBM,F,XOM)
	';
	die(); // Just display message and quit
}
/*
$aInput = explode($_REQUEST['symbol']);
$aSymbols = array();
foreach($aInput as $key=>$symbol){
	if (strpos($symbol, ".") > 0 || strpos($symbol, "-") > 0 || strpos($symbol, "/") > 0){
		$dot = str_replace(".", "/", $firstQuotes);


		$aSymbols =
	}

}
*/
// Wrap all the passed symbols in quotes
$firstQuotes = "'".$_REQUEST['symbol']."'";
//echo $firstQuotes."<br>";
//$dots = str_replace(".", "/", $firstQuotes);
//echo $dots."<br>";
//$dashes = str_replace("-", "/", $dots);
//echo $dashes."<br>";
//$symbols = str_replace(",", "','", $dashes);
//echo $symbols."<br>";
$symbols = str_replace(",", "','", $firstQuotes);

// Find all the funds that hold the passed symbol(s)
// Explode them for proper sorting
$query = "
	SELECT fsb.fund_id, SUBSTRING_INDEX(fsb.fund_id, '-', 1) as member_id, SUBSTRING_INDEX(fsb.fund_id, '-', -1) as fund_num, fsb.stockSymbol, fsb.totalShares, f.fund_symbol, f.fb_primarykey as fb_fund_key
	FROM members_fund_stratification_basic fsb, members_fund f
	WHERE fsb.stockSymbol IN (".$symbols.")
	AND fsb.totalShares > 0
	AND f.fund_id = fsb.fund_id
	AND f.active = 1
	ORDER BY fsb.stockSymbol, member_id + 0, fund_num + 0 ASC
";
try{
	$rsFunds = $mLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

//dump_rs($rsFunds, 1); // 1 indicated html output

// Step through all the results
$count = 1;
$current_symbol = "";
$new_symbol = true;
while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	// Test for new symbol
	if ($fund['stockSymbol'] != $current_symbol){
		$new_symbol = true;
		$current_symbol = $fund['stockSymbol'];
		$count = 1;
	}

	// Build table header for all funds holding this stock
	if ($new_symbol){
///////////////// Add trans wizard indicator ///////////////////////////////

		echo '
</table>
<h1>Active Funds Holding '.$current_symbol.'</h1>
<table border = "1" cellpadding = "10" cellspacing = "0">
	<tr>
		<th>Count</th>
		<th>Member ID</th>
		<th>Login Name</th>
		<th>Password</th>
		<th>Name</th>
		<th>Manager Key</th>
		<th>Fund ID</th>
		<th>Fund Symbol</th>
		<th>Shares</th>
		<th>Fund Key</th>
		<th>Last Login</th>
	</tr>
';
		$new_symbol = false;
	}

	// Get their member info
	$query = "
		SELECT username, name_first, name_last, fb_primarykey as fb_manager_key, last_login
		FROM members
		WHERE member_id = ".$fund['member_id']."
	";
	try{
		$rsMember = $mLink->prepare($query);
		$rsMember->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

//dump_rs($rsMember, 1); // 1 indicated html output
	$member = $rsMember->fetch(PDO::FETCH_ASSOC);

	// Assign massaged values
	$name = $member['name_first']." ".$member['name_last'];
	if (date("Y", $member['last_login']) == 1969){
		$login = "NEVER";
	}else{
		$login = date("m/d/Y @ h:i A T", $member['last_login']);
	}

	// Get their password
	$query = "
		SELECT password
		FROM system_authentication
		WHERE member_id = ".$fund['member_id']."
		ORDER BY timestamp DESC
	    LIMIT 1
	";
	try{
		$rsPassword = $mLink->prepare($query);
		$rsPassword->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Decrypt their password
	$pass = $rsPassword->fetch(PDO::FETCH_ASSOC);
	$encryptedPassword = $pass['password'];
	$password = trim(decrypt($encryptedPassword));
//echo $password,"<br>";

	// Write the row
	echo '
	<tr>
		<td align="right">'.$count.'</td>
		<td align="right">'.$fund['member_id'].'</td>
		<td>'.$member['username'].'</td>
		<td>'.$password.'</td>
		<td>'.$name.'</td>
		<td>'.$member['fb_manager_key'].'</td>
		<td align="right">'.$fund['fund_id'].'</td>
		<td>'.$fund['fund_symbol'].'</td>
		<td align="right">'.number_format($fund['totalShares'], 0, 0, ",").'</td>
		<td>'.$fund['fb_fund_key'].'</td>
		<td>'.$login.'</td>
	</tr>
	';

	// Increment counter
	$count++;

}

// Close 'er up.
echo "
</table><br><br>
";

// cest' fini

?>