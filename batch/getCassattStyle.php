<?php
// This commandline batch script generates a report of all Masters' model positions weights.
// *Note - this will not run within a web browser.

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

// Create the output files
$filename = "Model Allocations - ".date('Y-m-d @ g:i a').".csv";
$fp1 = fopen("/var/www/html/tmp/".$filename, "w");

// Write header rows
$headers = "Manager,Fund ID, Fund Symbol,Stock Symbol,Position Weight (%)\r\n";
fwrite($fp1, $headers);

// Get the funds we are tracking
$query = "
	SELECT *
	FROM composite_cassatt_list
	WHERE active = 1
	ORDER BY username, fund_symbol ASC
";
try{
	$rsFunds = $mLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Set starting port number, and range, for the API calls
$startPort = 52000; // API1
$endPort = 52099;
$port = rand($startPort, $endPort);

// Fund number counter (for display only)
$fund_number = 0;

while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	// Assign values to variables
	$fund_number++;
	$member_id 	= $fund['member_id'];
	$username	= trim($fund['username']);
	$fund_id  	= trim($fund['fund_id']);
	$fund_symbol= trim($fund['fund_symbol']);
	$date		= date('Ymd');
//$date = date('Ymd', strtotime("yesterday"));

	echo $fund_number." of ".$rsFunds->rowCount()." - Processing ".$username.":".$fund_symbol." (Fund ID ".$fund_id.")\n";

	// Run livePrice to get the current fund value
	$query = "livePrice|0|".$username."|".$fundID."|".$fundSymbol;
//echo $query."\n\n";

	// Execute an EXPECT script to call the API
	$cmd = '/var/www/html/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	exec($cmd);

	// Increment the port number for the next API call
	if ($port == $endPort){
		$port = $startPort;
	}else{
		$port++;
	}

	// Fire off a stratification rebuild to get the up-to-the-minute position weights (Removed & from end in order to force PHP to wait for it to complete)
	exec('/usr/bin/php /var/www/html/scripts/strat-build.php "fundID='.$fund_id.'" > /dev/null');

	// Take a few beats to let the stratification rebuild and API call finish
	sleep(5); // 5 seconds

	// Get the Fund's newly updated live price
	$query = "
		SELECT *
		FROM members_fund_liveprice
		WHERE fund_id = :fundID
	";
	try{
		$rsLiveprice = $mLink->prepare($query);
		$aValues = array(
			':fundID'		=> $fund_id
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsLiveprice->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$liveprice = $rsLiveprice->fetch(PDO::FETCH_ASSOC);

	// Get the Fund's newly updated positions weights
	$query = "
		SELECT stockSymbol, fundRatio
		FROM members_fund_stratification_basic
		WHERE fund_id = :fundID
		AND totalShares > 0
		ORDER BY fundRatio DESC
	";
	try{
		$rsPositions = $mLink->prepare($query);
		$aValues = array(
			':fundID'=> $fund_id
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsPositions->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($position = $rsPositions->fetch(PDO::FETCH_ASSOC)){

		// Assign variables
		$stock_symbol	= trim($position['stockSymbol']);
		$weight			= $position['fundRatio'] * 100;

		// Write the row - chr(160) places an unprintable character at the end, forcing Excel to see it as a string while not adding a visible character
		$row = '"'.$username.'","'.$fund_id.chr(160).'","'.$fund_symbol.'","'.$stock_symbol.'","'.$weight.'"';
		$row .= "\r\n";
		fwrite($fp1, $row);

	}

	// Finally, add a last line for their cash position
	$stock_symbol = "Cash";
	$weight = ($liveprice['cashValue'] / $liveprice['totalValue']) * 100;

	// Write the row
	$row = '"'.$username.'","'.$fund_id.chr(160).'","'.$fund_symbol.'","'.$stock_symbol.'","'.$weight.'"';
	$row .= "\r\n";
	fwrite($fp1, $row);

}

// Close 'er up
fclose($fp1);

$emailBody = "Model Allocations Report for Marketocracy Managers - ".date('m/d/y @ g:i a');

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Model Allocations Report for '.date('m/d/y @ g:i a');
$email->Body      = $emailBody;
$email->AddAddress('daniel.miroballi@mcm.marketocracy.com');
if ($sendToKen){
	$email->AddCC('ken.kam@marketocracy.com');
}
$email->AddCC('jeff.saunders@marketocracy.com');
$email->AddAttachment('/var/www/html/tmp/'.$filename);
$email->Send();

// Delete report
unlink('/var/www/html/tmp/'.$filename);

?>
