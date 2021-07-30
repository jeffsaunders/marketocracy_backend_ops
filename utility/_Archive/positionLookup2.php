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

// Massage the passed symbol string - First push them into an array
$aInput = explode(",", strtoupper($_REQUEST['symbol']));

// Assign the possible punctuation marks to an array
$aTokens = array(".","-","/");

// Create a destination array
$aSymbols = array();

// Step through the array of passed symbols
foreach($aInput as $symbol){
	$punctuated = false;

	// Check the symbol for a punctuation character (e.g. the dot in BRK.A)
	foreach ($aTokens as $token){

		// It does!
		if (stristr($symbol, $token) !== FALSE){
			$punctuated = TRUE;

			// Create a version of the symbol with each of the punctuations and push them onto the destination array - the result will be something like BRK.A, BRK-A, BRK/A, each added as an element of the array.
			// I do this so we look up each version below, in case they are stored with a different punctuation character than the one that was passed in the list of symbols.
			foreach ($aTokens as $punctuation){
				$modSymbol = str_replace($token, $punctuation, $symbol);
				array_push($aSymbols, $modSymbol);
			}
		}
	}

	// If there was no punctuation character then just add it to the destination array, as-is.
	if (!$punctuated){
		array_push($aSymbols, $symbol);
	}

}

// Now turn it back into a comma-delimited string for the IN clause in the subsequent query.
$sansQuotes = implode("','", $aSymbols);

// The above misses the first and last quote, add them
$symbols = "'".$sansQuotes."'";

// Wrap all the passed symbols in quotes - OLD version
//$firstQuotes = "'".strtoupper($_REQUEST['symbol'])."'";
//$symbols = str_replace(",", "','", $firstQuotes);

// Find all the funds that hold the massaged list of passed symbol(s)
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
$display_symbol = "";
$new_symbol = true;
while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	// Test for new symbol
	if ($fund['stockSymbol'] != $current_symbol){
		$new_symbol = true;
		$current_symbol = $fund['stockSymbol'];
		$display_symbol = $current_symbol;

		foreach ($aTokens as $token){
			if (stristr($current_symbol, $token) !== FALSE){
				$display_symbol = str_replace($token, '/', $current_symbol);
				break;
			}
		}
		$count = 1;
	}

	// Build table header for all funds holding this stock
	if ($new_symbol){
		echo '
</table>
<h1>Active Funds Holding '.$display_symbol.'</h1>
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
		<th>Trans Wizard</th>
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

//dump_rs($rsMember, 1); // 1 indicates html output
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
//echo $password."<br>";

	// See if they are queued up for the transition wizard
	$transWiz = 0; // Not, until they are

	// See if they have more than one fund first
	$query = "
		SELECT COUNT(*)
		FROM members_fund
		WHERE member_id = ".$fund['member_id']."
		AND active = 1
	";
	try{
		$rsFundCnt = $mLink->prepare($query);
		$rsFundCnt->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$fundCnt = $rsFundCnt->fetchColumn();  // 1 (true) if they are awaiting the transition wizard, 0 (false) if not.

	// if they have more than one fund, see if they are a basic member out of trial - if so, they are queued for the transition wizard
	if ($fundCnt > 1){

		$query = "
			SELECT COUNT(*)
			FROM members_subscriptions
			WHERE member_id = ".$fund['member_id']."
			AND product_id = 1
			AND trans_wiz IS NULL
			AND active = 1
		";
		try{
			$rsTransWiz = $mLink->prepare($query);
			$rsTransWiz->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$transWiz = $rsTransWiz->fetchColumn();  // 1 (true) if they are awaiting the transition wizard, 0 (false) if not.

	}

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
		<td>'.($transWiz > 0 ? "Queued" : "").'</td>
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