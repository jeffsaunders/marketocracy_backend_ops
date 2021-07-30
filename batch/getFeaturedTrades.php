<?php
// This commandline batch script generates a report of all trades in featured stocks (as defined by the featured_stocks table)by managers.
// *Note - this will not run within a web browser.

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

// Load mailer
require('/var/www/html/includes/PHPMailer/class.phpmailer.php');

// Create the output files
$filename = "Managers Trades in Featured Stocks - ".date('Y-m-d', strtotime("-1 day")).".csv";
//$fp = fopen("/var/www/html/tmp/".$filename, "w");

// Write header rows
$headers = "Manager,Fund,Kind,Stock,Closed,Shares,Price,CA\r\n";
//fwrite($fp, $headers);

// How many days back do we go?
switch (date('w')){

	case "1": // Mon
		$startDate = strtotime("-3 day 12:00 AM"); // Fri
		break;
//	case "2": // Tue
//		$startDate = strtotime("-2 day 12:00 AM"); // Fri, Mon, Tue
//		break;
	default: // Tue-Fri
		$startDate = strtotime("-1 day 12:00 AM"); // Yesterday
		break;
}
$startDate = strtotime("-100 days 12:00 AM");
$endDate = strtotime("-1 day 11:59:59 PM");
//echo $startDate."\n".$endDate."\n";

// Get the stock symbols we are tracking
// For some reason PDO is not automatically placing quotes around the substituted date values in this query so I've added them manually
$query = "
	SELECT *
	FROM featured_stocks
	WHERE start_date <= ':startDate'
	AND (end_date >= ':endDate' OR end_date IS NULL)
	ORDER BY symbol ASC
";
try{
	$rsStocks = $rLink->prepare($query);
	$aValues = array(
		':startDate' => date('Y-m-d'),
		':endDate'	 => date('Y-m-d')
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;die();
	$rsStocks->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Build a string of stock symbols for subsequent queries
$symbols = "";
while($stock = $rsStocks->fetch(PDO::FETCH_ASSOC)){
	$symbols .= "'". $stock['symbol'] . "',";
}

// Pop the trailing comma off
$symbols = substr($symbols, 0, -1);
//echo $symbols;

// Get the composite funds held by the managers
$query = "
	SELECT c.member_id, c.username, m.name_first, m.name_last, c.fund_id, c.fund_symbol
	FROM composite_cassatt_list c, members m
	WHERE c.active = 1
	AND c.member_id = m.member_id
	ORDER BY c.username ASC
";
try{
	$rsFunds = $mLink->prepare($query);
	$rsFunds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Set a flag for whether any trades were found
$tradesFound = false;

while($fund = $rsFunds->fetch(PDO::FETCH_ASSOC)){

	// Assign fund values to variables
	$member_id 	= $fund['member_id'];
	$username	= trim($fund['username']);
	$first_name	= trim($fund['name_first']);
	$last_name	= trim($fund['name_last']);
	$fund_id  	= trim($fund['fund_id']);
	$fund_symbol= trim($fund['fund_symbol']);

	// Get the trades for this fund
	$query = "
		SELECT *
		FROM members_fund_trades
		WHERE fund_id = :fundID
		AND unix_closed >= :startDate
		AND unix_closed <= :endDate
		AND stockSymbol IN (:symbols)
		ORDER BY unix_closed ASC
	";
	try{
		$rsTrade = $mLink->prepare($query);
		$aValues = array(
			':fundID'		=> $fund_id,
			':startDate' 	=> $startDate,
			':endDate'	 	=> $endDate,
			':symbols'		=> $symbols
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		echo $preparedQuery;//die();
		$rsTrade->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$trade = $rsTrade->fetch(PDO::FETCH_ASSOC);
print_r($trade);die();




	while($trade = $rsTrades->fetch(PDO::FETCH_ASSOC)){

print_r($trade);
//		if ($rsTrades->rowCount() > 0){

			$tradesFound = true;

			// Assign trade values to variables
			$stock_symbol	= trim($trade['stockSymbol']);
			$closed			= date('Y-m-d H:i:s', $trade['unix_closed']);
			$shares_filled	= $trade['sharesFilled'];
			$price			= $trade['price'];
			$ca				= ($trade['createdByCA'] == 1 ? "Yes" : "");
			$kind			= trim($trade['buyOrSell']);

			// Write the row
			$row = '"'.$username.'","'.$fund_symbol.'","'.$kind.'","'.$stock_symbol.'","'.$closed.'","'.$shares_filled.'","'.$price.'","'.$ca.'"';
			$row .= "\r\n";
//			fwrite($fp, $row);
echo $row;
//		}

	}


}

if (!$tradesFound){

	$row = "No Trades Placed";
	$row .= "\r\n";
//	fwrite($fp, $row);

echo $row;
}
/*



while($stock = $rsStocks->fetch(PDO::FETCH_ASSOC)){

	// Assign cassatt values to variables
	$member_id 	= $fund['member_id'];
	$username	= trim($fund['username']);
	$fund_id  	= trim($fund['fund_id']);
	$fund_symbol= trim($fund['fund_symbol']);

	// Get the trades for this fund
	$query = "
		SELECT *
		FROM members_fund_trades
		WHERE fund_id = :fundID
		AND unix_closed >= :startDate
		AND unix_closed <= :endDate
		ORDER BY unix_closed ASC
	";
	try{
		$rsTrades = $mLink->prepare($query);
		$aValues = array(
			':fundID'		=> $fund_id,
			':startDate' 	=> $startDate,
			':endDate'	 	=> $endDate
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsTrades->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($trade = $rsTrades->fetch(PDO::FETCH_ASSOC)){

		if ($rsTrades->rowCount() > 0){

			// Assign trade values to variables
			$stock_symbol	= trim($trade['stockSymbol']);
			$closed			= date('Y-m-d H:i:s', $trade['unix_closed']);
			$shares_filled	= $trade['sharesFilled'];
			$price			= $trade['price'];
			$ca				= ($trade['createdByCA'] == 1 ? "Yes" : "");
			$kind			= trim($trade['buyOrSell']);

			// Write the row
			$row = '"'.$username.'","'.$fund_symbol.'","'.$kind.'","'.$stock_symbol.'","'.$closed.'","'.$shares_filled.'","'.$price.'","'.$ca.'"';
			$row .= "\r\n";
			fwrite($fp1, $row);

		}
	}

	// Get open tickets for this fund
	$query = "
		SELECT *
		FROM members_fund_tickets
		WHERE fund_id = :fundID
		AND status = :status
		ORDER BY openned ASC
	";
	try{
		$rsTickets = $mLink->prepare($query);
		$aValues = array(
			':fundID'		=> $fund_id,
			':status'	 	=> 'pending'
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsTickets->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($ticket = $rsTickets->fetch(PDO::FETCH_ASSOC)){

		if ($rsTickets->rowCount() > 0){

			// Assign ticket values to variables
			$stock_symbol	= trim($ticket['symbol']);
			$opened			= date('Y-m-d H:i:s', $ticket['openned']);
			$shares_ordered	= $ticket['shares'];
			$shares_filled	= $ticket['sharesFilled'];
			$price			= round($ticket['quote_price'], 2);
			$action			= ucfirst(trim($ticket['action']));

			// Write the row
			$row = '"'.$username.'","'.$fund_symbol.'",Open,"'.$opened.'","'.$action.'","'.$stock_symbol.'","'.$shares_ordered.'","'.$shares_filled.'",N/A,"'.$price.'"';
			$row .= "\r\n";
			fwrite($fp2, $row);

		}
	}
}

// Close 'er up
fclose($fp1);
fclose($fp2);

$emailBody = "Trades and Open Tickets Reports for Marketocracy Managers - ".date('m/d/y @ g:i a', $endDate);

// Mail it
$email = new PHPMailer();
$email->From      = 'it@marketocracy.com';
$email->FromName  = 'Marketocracy IT';
$email->Subject   = 'Managers\' Trades and Open Tickets Report for '.date('m/d/y @ g:i a', $endDate);
$email->Body      = $emailBody;
$email->AddAddress('daniel.miroballi@mcm.marketocracy.com');
if ($sendToKen){
	$email->AddCC('ken.kam@marketocracy.com');
}
$email->AddCC('jeff.saunders@marketocracy.com');
//$email->AddAttachment('/var/www/html/tmp/MastersTrades-'.date('Y-m-d @ g:i a').'.csv');
//$email->AddAttachment('/var/www/html/tmp/MastersTickets-'.date('Y-m-d @ g:i a').'.csv');
$email->AddAttachment('/var/www/html/tmp/'.$filename1);
$email->AddAttachment('/var/www/html/tmp/'.$filename2);
$email->Send();

// Delete report
unlink('/var/www/html/tmp/'.$filename1);
unlink('/var/www/html/tmp/'.$filename2);

*/

?>
