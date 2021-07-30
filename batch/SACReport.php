<?php
// This commandline batch script generates a report of all trades for specified fund keys for SAC.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

if (!isset($_REQUEST['input'])){
	echo "You must specify an input file (e.g. SACReport.php \"input=filename.csv\")\n";
	die();
}

if (!file_exists($_REQUEST['input'])){
	echo "Input file does not exist\n";
	die();
}

// Start me up
session_start();

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


//Open the input file
$input = fopen($_REQUEST['input'], "r");
if (!$input){
	echo "Unable to successfully open input file - aborting!\n";
	die();
}

// Create the output file
$fp1Name = "/var/www/html/tmp/SACTrades-".date('Y-m-d@g:ia').".csv";
$fp2Name = "/var/www/html/tmp/SACAlphaBeta-".date('Y-m-d@g:ia').".csv";
$fp1 = fopen($fp1Name, "w");
$fp2 = fopen($fp2Name, "w");

// Write header rows
$headers = "Fund_Key,Total_Shares,Gains\r\n";
fwrite($fp1, $headers);
$headers = "Fund_Key,Alpha,Beta,RSquared\r\n";
fwrite($fp2, $headers);

// Step through the passed fund keys
while (!feof($input)) {

	$fundkey = fgets($input);

	// Get the trades stratification for this fund
	$query = "
	    SELECT f.fb_primarykey as fundkey, s.totalShares, s.gains
	    FROM members_fund f, members_fund_stratification_basic s
	    WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
		AND s.fund_id = f.fund_id
	";
	try{
		$rsStrat = $mLink->prepare($query);
		$aValues = array(
			':fundKey'	=> "\"X'".trim($fundkey)."'\""
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsStrat->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($strat = $rsStrat->fetch(PDO::FETCH_ASSOC)){

		if ($rsStrat->rowCount() > 0){

			// Assign trade values to variables
			$totalShares	= $strat['totalShares'];
			$gains			= $strat['gains'];

			// Write the row
			$row = '"'.trim($fundkey).'","'.$totalShares.'","'.$gains.'"';
			$row .= "\r\n";
			fwrite($fp1, $row);

		}
	}

	// Get the AlphaBeta values for this fund
	$query = "
	    SELECT f.fb_primarykey as fundkey, ab.thirtyDayAlphaSkipAAR AS alpha, ab.thirtyDayBetaSkip AS beta, ab.thirtyDayRSquaredSkip AS rsquared
	    FROM members_fund f, members_fund_alphabeta ab
	    WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
		AND ab.fund_id = f.fund_id
	";
	try{
		$rsAlphaBeta = $mLink->prepare($query);
		$aValues = array(
			':fundKey'	=> "\"X'".trim($fundkey)."'\""
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsAlphaBeta->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($alphaBeta = $rsAlphaBeta->fetch(PDO::FETCH_ASSOC)){

		if ($rsAlphaBeta->rowCount() > 0){

			// Assign ticket values to variables
			$alpha		= $alphaBeta['alpha'];
			$beta		= $alphaBeta['beta'];
			$rSquared	= $alphaBeta['rsquared'];

			// Write the row
			$row = '"'.trim($fundkey).'","'.$alpha.'","'.$beta.'","'.$rSquared.'"';
			$row .= "\r\n";
			fwrite($fp2, $row);

		}
	}
}

// Close 'er up
fclose($fp1);
fclose($fp2);

$emailBody = "SAC Reports - ".date('m/d/y @ g:i a');

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'SAC Reports for '.date('m/d/y @ g:i a');
$email->Body      = $emailBody;
//$email->AddAddress('daniel.miroballi@mcm.marketocracy.com');
$email->AddAddress('jeff.saunders@marketocracy.com');
//$email->AddAttachment('/var/www/html/tmp/MastersTrades-'.date("Ymd", strtotime("-1 day 11:59:59 PM")).'.csv');
//$email->AddAttachment('/var/www/html/tmp/MastersTickets-'.date("Ymd", strtotime("-1 day 11:59:59 PM")).'.csv');
//$email->AddAttachment("/var/www/html/tmp/SACTrades-".date('Y-m-d @ g:i a').".csv");
//$email->AddAttachment("/var/www/html/tmp/SACAlphaBeta-".date('Y-m-d @ g:i a').".csv");
//$email->AddAttachment($fp1Name);
//$email->AddAttachment($fp2Name);
$email->Send();

// Delete report
//unlink('/var/www/html/tmp/MastersTrades-'.date("Ymd", strtotime("-1 day 11:59:59 PM")).'.csv');
//unlink('/var/www/html/tmp/MastersTickets-'.date("Ymd", strtotime("-1 day 11:59:59 PM")).'.csv');
//unlink("/var/www/html/tmp/SACTrades-".date('Y-m-d @ g:i a').".csv");
//unlink("/var/www/html/tmp/SACAlphaBeta-".date('Y-m-d @ g:i a').".csv");
//unlink($fp1Name);
//unlink($fp2Name);

?>
