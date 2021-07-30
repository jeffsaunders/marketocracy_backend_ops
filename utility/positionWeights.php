<?php
/*
The purpose of this script is to display a members current stratification weights for Dan to copy when creating a mirrored fund in FOLIOfn.
*Note - this must be run in a web browser.
*/

// Define some system settings
date_default_timezone_set('America/New_York');
setlocale(LC_MONETARY, 'en_US.UTF-8');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Set up the page
echo '
<head>
	<title>Position Weight Lookup Tool</title>
</head>
<html>
<body style="font-family:Arial, Helvetica, sans-serif; font-size:14px;">
';

// Display the form
echo '
<form action="" name="positionWeights" id="positionWeights" method="POST">
	Enter Member\'s username:
	<input type="text" name="username" size="50" autofocus="autofocus" value="">
	<input type="submit">
</form>
';

// If the username was passed, show results
if (isset($_REQUEST['username']) && $_REQUEST['username'] != ""){

	// Run long enough
	set_time_limit(900); // 15 minutes

	// Load encryption functions
//	require("/var/www/html/includes/crypto.php");

	// Connect to MySQL
	require("/var/www/html/includes/dbConnectPDO.php");

	// Get newest system config values
//	require("/var/www/html/includes/getConfigPDO.php");

	// Assign passed variable(s)
	$username = $_REQUEST['username'];

	// Get member's funds' stratification information (weights)
	$query = "
		SELECT fsb.fund_id, fsb.stockSymbol, fsb.fundRatio, f.fund_symbol, m.username
		FROM members_fund_stratification_basic fsb, members_fund f, members m
		WHERE m.username = '".$username."'
		AND fsb.totalShares > 0
		AND f.fund_id = fsb.fund_id
		AND m.member_id = f.member_id
		AND f.active = 1
		ORDER BY fsb.fund_id ASC, fsb.fundRatio DESC
	";
//echo $query;
	try{
		$rsWeights = $mLink->prepare($query);
		$rsWeights->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
//dump_rs($rsWeights, 1); // 1 indicates html output

	// Initialize vars
	$fundID = "";
	$totalValue = "";

	// Who we lookin' at?
	echo "<h3>Username: ".$username."</h3>";

	// Loop through the weight results
	while ($weights = $rsWeights->fetch(PDO::FETCH_ASSOC)){

		// If the fund ID changes we are onto a new fund
		if ($weights['fund_id'] != $fundID){

			// If this is not the first fund, then display the previous fund's total value
			if ($fundID != ""){
				echo '</table><strong>Total Value: '.money_format('%.2n', $totalValue).'</strong><br><br><br>';
			}

			$fundID = $weights['fund_id'];

			// Get the current fund's values
			$query = "
				SELECT cashValue, totalValue
				FROM members_fund_liveprice
				WHERE fund_id = '".$fundID."'
			";
// Originally pulled this from members_fund_pricing but that yielded yesterday's closing values - switched to liveprice to get the most recent values
// These query commands don't apply to liveprice
//				ORDER BY unix_date DESC
//				LIMIT 1
			try{
				$rsValues = $mLink->prepare($query);
				$rsValues->execute();
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

			$values = $rsValues->fetch(PDO::FETCH_ASSOC);

			// Assign and calculate values
			$cashValue = $values['cashValue'];
			$totalValue = $values['totalValue'];
			$cashWeight = $cashValue/$totalValue;

			// Start the new fund's output
			echo 'Fund ID: '.$fundID.' ('.$weights['fund_symbol'].')<br>';
			echo '<table cellspacing="0" cellpadding="3" border="1" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; border:1px solid #000000;">';
			echo '<tr><th>Symbol</th><th>Weight</th></tr>';
			echo '<tr>';
			echo '<td>Cash</td>';
			if ($cashWeight < 0){
				echo '<td style="color:red;">'.($cashWeight*100).'</td>'; // Display negative cash weight in red
			}else{
				echo '<td>'.($cashWeight*100).'</td>';
			}
			echo '</tr>';

		}

		// Output each position weight
		echo '<tr>';
		echo '<td>'.$weights['stockSymbol'].'</td>';
		echo '<td>'.($weights['fundRatio']*100).'</td>';
		echo '</tr>';

	}

	// If the member was not found, say so
	if ($fundID == ""){
		echo "<h2>Member Not Found.<h2>";
	// Otherwise close out the table displaying the weights (including the final fund's total value)
	}else{
		echo '</table><strong>Total Value: '.money_format('%.2n', $totalValue).'</strong>';
	}

	// Close out the html
	echo "</body></html>";

}

// cest' fini

?>