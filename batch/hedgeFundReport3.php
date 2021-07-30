<?php
// This commandline batch script generates a report of all trades and positions for specified fund keys for Hedge Funds (SAC, etc.).
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Test passed values
if (!isset($_REQUEST['input'])){
	echo "You must specify an input file (e.g. SACReport.php \"input=filename.csv\")\n";
	die();
}

if (!isset($_REQUEST['fundname'])){
	echo "You must specify a Hedge Fund name file (e.g. SACReport.php \"fundname=SAC\")\n";
	die();
}

if (!file_exists($_REQUEST['input'])){
	echo "Input file does not exist\n";
	die();
}

// NEED TO SANITIZE PASSED DATE VALUE TO ASSURE VALIDITY HERE........

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

// Assign passed values
$fileName = trim($_REQUEST['input']);
$fundName = trim($_REQUEST['fundname']);
if (isset($_REQUEST['date'])){
	$reportDate = trim($_REQUEST['date']);
}else{
	$reportDate = date('Ymd', strtotime("yesterday"));
}
$reportYear = substr($reportDate, 0, 4);
$reportMonth = substr($reportDate, 4, 2);
$reportDay = substr($reportDate, 6, 2);
$rowDate = $reportMonth."/".$reportDay."/".$reportYear;

//Open the input file
$input = fopen($fileName, "r");
if (!$input){
	echo "Unable to successfully open input file - aborting!\n";
	die();
}

// Name the output files
$fpPOName = "/var/www/html/tmp/".$fundName."-Positions-".$reportYear."-".$reportMonth."-".$reportDay."-Orig.csv";
$fpPAName = "/var/www/html/tmp/".$fundName."-Positions-".$reportYear."-".$reportMonth."-".$reportDay."-Anon.csv";

$fpTOName = "/var/www/html/tmp/".$fundName."-Trades-".$reportYear."-".$reportMonth."-".$reportDay."-Orig.csv";
$fpTAName = "/var/www/html/tmp/".$fundName."-Trades-".$reportYear."-".$reportMonth."-".$reportDay."-Anon.csv";

// Create the outputs files
$fpPO = fopen($fpPOName, "w");
$fpPA = fopen($fpPAName, "w");

$fpTO = fopen($fpTOName, "w");
$fpTA = fopen($fpTAName, "w");

// Write header rows
$headers = "Fund_Key,Login Name,Fund Symbol,Current Date,Stock Symbol On Date,Position Date,Stock CUSIP,Stock Current Shares,Stock Price,Stock Value\r\n";
fwrite($fpPO, $headers);
$headers = "Fund_Key,Current Date,Stock Symbol On Date,Position Date,Stock CUSIP,Stock Current Shares,Stock Price,Stock Value\r\n";
fwrite($fpPA, $headers);

$headers = "Fund_Key,Login Name,Fund Symbol,Current Date,Trade Kind,Trade Symbol,Trade Date,Trade Shares,Trade Net Price,Trade Value,Created by CA,Current Symbol,Trade Ticket Key\r\n";
fwrite($fpTO, $headers);
$headers = "Fund_Key,Current Date,Trade Kind,Trade Symbol,Trade Date,Trade Shares,Trade Net Price,Trade Value,Created by CA,Current Symbol,Trade Ticket Key\r\n";
fwrite($fpTA, $headers);

// Step through the passed fund keys
while (!feof($input)) {
//while (1==2) {

	$fundkey = fgets($input);
//$fundkey = "c95e05f35290ee29c0a80132"; //Testing
//$fundkey = "FFFF195C50D9431DC0A80132";
//$fundkey = "5ADB06433AB3B411C0A801E0";
echo $fundkey;

	// Get the positions for this fund on the first day of every month since 1/1/12
	$query = "
	    SELECT f.fb_primarykey as fundkey, f.fund_symbol, m.username, p.*
	    FROM members_fund f, members m, members_fund_positions p
	    WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
		AND p.fund_id = f.fund_id
		AND m.member_id = f.member_id
		AND p.unix_date >= 1325412000
		AND DAYOFMONTH(FROM_UNIXTIME(p.unix_date)) = 1
		ORDER BY p.unix_date ASC, stockSymbol ASC
 		";
/*
	// Get (almost) all the stuff
	// Note - subquery returns the most recent date on or before the passed date.  Some members are less active and only get position data once a week during inactive periods.
	// Also - had to forgo PDO Variable Bounding as it just doesn't like the FrontBase Key syntax AT ALL! (me either - JS)
	$query = "
	    SELECT f.fb_primarykey as fundkey, f.fund_symbol, m.username, p.*
	    FROM members_fund f, members m, members_fund_positions p
	    WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
		AND p.fund_id = f.fund_id
		AND m.member_id = f.member_id
		AND p.date = (SELECT MAX(date) date
					  FROM members_fund_positions
					  WHERE fund_id = p.fund_id
					  AND date <= ".$reportDate.")
	";
//WHERE f.fb_primarykey = :fundKey
//WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
//AND p.date = :reportDate
*/
	try{
		$rsPositions = $mLink->prepare($query);
		$aValues = array(
			':fundKey'		=> "\"X'".trim($fundkey)."'\"",
			':reportDate'	=> $reportDate
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsPositions->execute($aValues);
//		$rsPositions->execute(); // Sans variable bounding
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
//echo $error;
//echo sizeOf($rsPositions)."\n";
//echo $rsPositions->fetchColumn()."\n";
//$position = $rsPositions->fetch(PDO::FETCH_ASSOC);
//print_r($position); die();



//if ($fundkey == "00494AAE43F39EF5C0A80134"){
//	die();
//	break;
//}





	while($position = $rsPositions->fetch(PDO::FETCH_ASSOC)){

		// Store these off for later
		$fundID = $position['fund_id'];
		$userName = $position['username'];
		$fundSymbol = $position['fund_symbol'];

		$positionDate = substr($position['date'], 4, 2)."/".substr($position['date'], 6, 2)."/".substr($position['date'], 0, 4);

		// Start building the rows
		$rowPO = '"'.trim($fundkey).'","'.$position['username'].'","'.$position['fund_symbol'].'","'.$rowDate.'","'.$position['stockSymbol'].'","'.$positionDate.'"';
		$rowPA = '"'.trim($fundkey).'","'.$rowDate.'","'.$position['stockSymbol'].'","'.$positionDate.'"';

		// Get the CUSIP for the stock
		$query = "
		    SELECT Cusip
		    FROM cusip_feed
		    WHERE Symbol = :symbol
		";
		try{
			$rsCUSIP = $fLink->prepare($query);
			$aValues = array(
				':symbol'	=> $position['stockSymbol']
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;die();
			$rsCUSIP->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$CUSIP = $rsCUSIP->fetch(PDO::FETCH_ASSOC);

		// Finish building rows
		$rowPO .= ',"'.$CUSIP['Cusip'].'","'.$position['shares'].'","'.$position['price'].'","'.$position['value'].'"';
		$rowPA .= ',"'.$CUSIP['Cusip'].'","'.$position['shares'].'","'.$position['price'].'","'.$position['value'].'"';

		$rowPO .= "\r\n";
		$rowPA .= "\r\n";

		fwrite($fpPO, $rowPO);
		fwrite($fpPA, $rowPA);

//echo $rowPO."\n";
//echo $rowPA;



	}




//	fclose($fpPO);
//	fclose($fpPA);
//fclose($input);


// Now build the trades files


// Re-open the input file
//$input = fopen($fileName, "r");

// Step through the passed fund keys
//while (!feof($input)) {

//	$fundkey = fgets($input);
//$fundkey = "c95e05f35290ee29c0a80132"; //Testing
//$fundkey = "FFFF195C50D9431DC0A80132";
//$fundkey = "5ADB06433AB3B411C0A801E0";
//echo $fundkey."\n";




















		// Now get the trades for this fund on specified date

		// Start building the rows
	//	$rowTO = '"'.trim($fundkey).'","'.trim($userName).'","'.trim($fundSymbol).'","'.$rowDate.'"';
	//	$rowTA = '"'.trim($fundkey).'","'.$rowDate.'"';

	//	$fundID = $position['fund_id'];

//		$query = "
//		    SELECT *
//		    FROM members_fund_trades
//		    WHERE fund_id = :fundID
//			AND stockSymbol = :stockSymbol
//		";
		$query = "
		    SELECT *
		    FROM members_fund_trades
		    WHERE fund_id = '".$fundID."'
			AND unix_closed >= 1325412000
			ORDER BY unix_closed ASC, stockSymbol ASC
		";
		try{
			$rsTrades = $mLink->prepare($query);
	//			$aValues = array(
	//				':fundID'		=> $fundID,
	//				':reportDate'	=> $reportDate,
	//				':stockSymbol'	=> $position['stockSymbol']
	//			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;die();
			$rsTrades->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// See if there were any trades returned
/*		if ($rsTrades->fetchColumn() < 1){

			$rowTO = '"'.trim($fundkey).'","'.trim($userName).'","'.trim($fundSymbol).'","'.$rowDate.'","No Trades"';
			$rowTA = '"'.trim($fundkey).'","'.$rowDate.'","No Trades"';

			$rowTO .= "\r\n";
			$rowTA .= "\r\n";

			fwrite($fpTO, $rowTO);
			fwrite($fpTA, $rowTA);

	//echo $rowTO."\n";
	//echo $rowTA."\n\n";
			continue; // Bail, we're done with this fund

		}
*/
//print_r($rsTrades->fetch(PDO::FETCH_ASSOC));die();
		while($trade = $rsTrades->fetch(PDO::FETCH_ASSOC)){

			echo ".";

			$tradeDate = substr($trade['closed'], 4, 2)."/".substr($trade['closed'], 6, 2)."/".substr($trade['closed'], 0, 4);

			// Start building the rows
			$rowTO = '"'.trim($fundkey).'","'.trim($userName).'","'.trim($fundSymbol).'","'.$rowDate.'"';
			$rowTA = '"'.trim($fundkey).'","'.$rowDate.'"';

			// Finish building rows
			$rowTO .= ',"'.$trade['buyOrSell'].'","'.$trade['stockSymbol'].'","'.$tradeDate.'","'.$trade['sharesFilled'].'","'.$trade['price'].'","'.round($trade['net'], 2).'","'.($trade['createdByCA'] = 0 ? "No" : "Yes").'","'.$trade['stockSymbol'].'","'.$trade['ticketKey'].'"';
			$rowTA .= ',"'.$trade['buyOrSell'].'","'.$trade['stockSymbol'].'","'.$tradeDate.'","'.$trade['sharesFilled'].'","'.$trade['price'].'","'.round($trade['net'], 2).'","'.($trade['createdByCA'] = 0 ? "No" : "Yes").'","'.$trade['stockSymbol'].'","'.$trade['ticketKey'].'"';

			$rowTO .= "\r\n";
			$rowTA .= "\r\n";

			fwrite($fpTO, $rowTO);
			fwrite($fpTA, $rowTA);

//echo $rowTO."\n";
//echo $rowTA."\n\n";

		}
		echo "\n";


//	}

//fclose($fpPO);
//fclose($fpPA);
//fclose($fpTO);
//fclose($fpTA);
//die();
//}


}

// Close 'er up
fclose($fpPO);
fclose($fpPA);
fclose($fpTO);
fclose($fpTA);
fclose($input);

/*
$emailBody = "Hedge Fund Reports for ".$fundName." - ".$rowDate;

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Hedge Fund Reports for '.$fundName.' - '.$rowDate;
$email->Body      = $emailBody;
//$email->AddAddress('ken@marketocracy.com');
$email->AddAddress('jeff.saunders@marketocracy.com');
$email->AddAttachment($fpPOName);
$email->AddAttachment($fpPAName);
$email->AddAttachment($fpTOName);
$email->AddAttachment($fpTAName);
$email->Send();

// Delete report
//unlink($fpPOName);
//unlink($fpPAName);
//unlink($fpTOName);
//unlink($fpTAName);
*/

?>
