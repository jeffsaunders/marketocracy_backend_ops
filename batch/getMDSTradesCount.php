<?php
// This commandline batch script generates a report of the quantity of non-CA generated trades performed for each fund for stated time periods.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

// Create the output file
$fp = fopen("/var/www/html/tmp/MDS_Trades_Count_".date('Ymd').".csv", "w");

// Write header rows
$headers = "FundKey,AsOfDate,Trades_1,Trades_3,Trades_5,Trades_10\r\n";
fwrite($fp, $headers);

// Calculate period timestamps
$aTimestamps = array();
$aTimestamps[0] = strtotime("-1 year");
$aTimestamps[1] = strtotime("-3 years");
$aTimestamps[2] = strtotime("-5 years");
$aTimestamps[3] = strtotime("-10 years");

// Start a row counter and timer
$rowCount = 0;
$startTime = time();

// Get the funds we are looking for
$query = "
	SELECT *
	FROM ranking_fundkeys
";
try{
	$rsFunds = $rLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	// See if we have this fund
	$query = "
		SELECT count(*) as count FROM members_fund
		WHERE fb_primarykey = 'X\'".$fund['fundkey']."\''
	";
	try{
		$rsFound = $mLink->prepare($query);
		$rsFound->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$found = $rsFound->fetch(PDO::FETCH_ASSOC);
	$exists = $found['count'];

//echo $fund['fundkey']."\n";
	if ($exists < 1){  // Not found
		continue;
    }else{  // Got one!

		// Start the row string
		$row = '"'.$fund['fundkey'].'","'.date('n/j/Y').'"';

		//Grab the number of trades for each defined period
		for ($x = 0; $x < sizeof($aTimestamps); $x++){

			$query = "
				SELECT count(*) as count FROM members_fund f, members_fund_trades t
				WHERE f.fund_id = t.fund_id
				AND f.fb_primarykey = 'X\'".$fund['fundkey']."\''
				AND t.createdByCA = 0
				AND t.ticketStatus = 'closed'
				AND t.unix_closed > ".$aTimestamps[$x]."
			";
			try{
				$rsTrades = $mLink->prepare($query);
				$rsTrades->execute();
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

			$trades = $rsTrades->fetch(PDO::FETCH_ASSOC);
			$count = $trades['count'];

			// append to the row
			$row .= ',"'.$count.'"';

		}

		// Finish off the row and write it
		$row .= "\r\n";
		fwrite($fp, $row);
		$rowCount++;
//echo $row;// die();
	}
}

// Close 'er up
fclose($fp);

// Calculate the elapsed time
$elapsedSeconds = time() - $startTime;

echo $rowCount." Rows Written to <strong>/var/www/html/tmp/MDS_Trades_Count_".date('Ymd').".csv</strong> in ".$elapsedSeconds." Seconds\n";

?>
