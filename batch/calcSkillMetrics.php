<?php

// This one-off utility is designed to calculate the skill metrics values for ranked funds and append them to their ranking report records for analysis.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
//parse_str($argv[1], $_REQUEST);

// Determine if this copy is to be emailed to Ken as well as Dan
//$sendToKen = false;
//if (isset($_REQUEST['sendToKen']) && strtoupper($_REQUEST['sendToKen']) == "YES"){
//	$sendToKen = true;
//}

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");


// Get the ranked member and fund IDs
$query = "
	SELECT member_id, fund_id
	FROM rank_report_pro_KEN
";
try{
	$rsFunds = $mLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	$member_id = $fund['member_id'];
	$fund_id = $fund['fund_id'];

	// Get email address...
	$query = "
		SELECT email
		FROM members
		WHERE member_id = :member_id
	";
	try{
		$rsMember = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $member_id

		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsMember->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$member = $rsMember->fetch(PDO::FETCH_ASSOC);
	$email = $member['email'];

	// ...and needed raw stratification data
	$query = "
		SELECT totalShares, gains
		FROM members_fund_stratification_basic
		WHERE fund_id = :fund_id
		ORDER BY totalShares ASC
	";
	try{
		$rsStrat = $mLink->prepare($query);
		$aValues = array(
			':fund_id'      => $fund_id

		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsStrat->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$aGains = array();
	$gainCnt = 0;
	while($strat = $rsStrat->fetch(PDO::FETCH_ASSOC)){
		$gainCnt++;
		$aGains[$gainCnt] = $strat['gains'];

	}
	$aMembersWorking[$memberID]['funds'][$fundID]['positions'] = $gainCnt;
	$aMembersWorking[$memberID]['funds'][$fundID]['gains'] = $aGains;

	foreach($aMembersWorking[$memberID]['funds'] as $fundID=> $aStuff){

		$posGainCnt = 0;
		$negGainCnt = 0;

		$posGainTotal = 0;
		$negGainTotal = 0;

		foreach($aStuff['gains'] as $key=>$gain){
			if($gain < 0){
				$negGainCnt++;
				$negGainTotal = $negGainTotal + $gain;
			}elseif($gain > 0){
				$posGainCnt++;
				$posGainTotal = $posGainTotal + $gain;
			}
		}


		$winningPercent = $posGainCnt / $aStuff['positions'];
		$gainLossRatio = $posGainTotal / abs($negGainTotal);

		$nGain = $posGainTotal / $posGainCnt;
		$dLoss = $negGainTotal / $negGainCnt;

		$avgGainLoss = $nGain / abs($dLoss);


	}

	// Insert calculated skill metric data and email address into the special ranking data table
	$query = "
		UPDATE rank_report_pro_KEN
		SET winning_percent = :winning_percent,
			gain_loss_ratio = :gain_loss_ratio,
			email = :email
			WHERE fund_id = :fund_id
	";
	try{
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':fund_id'			=> $fund_id,
			':winning_percent'	=> $winningPercent,
			':gain_loss_ratio'	=> $avgGainLoss,
			':email'			=> $email
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

//echo "Winning % = ".round(($winningPercent * 100), 2)."\n";
//echo "Gan Loss Ratio = ".$gainLossRatio."\n";
//echo "nGain = ".$nGain."\n";
//echo "dLoss = ".$dLoss."\n";
//echo "Avg Gain Loss = ".round($avgGainLoss, 2)."\n";
//die();

}

?>