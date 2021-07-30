<?php
// This commandline batch script checks for any open trade tickets and queries their status, updating that status (mostly closing) if it has changed.
// By running it as a CRON you can set the checking and updating interval
// Example:
//	*/10 * * * * /usr/bin/php /var/www/html/batch/getTicketStatus.php
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start me up
session_start();

// Load some useful functions
//require("../includes/systemFunctions.php");
require("/var/www/html/includes/systemFunctionsPDO.php");
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfigPDO.php");

// Check for open tickets
if (isMarketOpen(time(), $mLink, "none")){ // Do this only if the markets are open
	// Only grab open tickets that are at least 15 minutes old (feed delay - can't close anyway)
	$query = "
		SELECT ticket_key, cancel_status
		FROM ".$fund_tickets_table."
		WHERE (status = 'pending'
			   OR status = 'open'
		)
		AND openned <= UNIX_TIMESTAMP() - ".$feed_delay."
		AND ticket_key <> ''
	";
}else{
	$query = "
		SELECT ticket_key, cancel_status
		FROM ".$fund_tickets_table."
		WHERE (status = 'pending'
			   OR status = 'open'
		)
		AND ticket_key <> ''
	";
}
//		AND cancel_status <> 0

try{
	$rs_tickets = $mLink->prepare($query);
	$rs_tickets->execute();
}
catch(PDOException $error){
// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$startPort = 53100;
$endPort = 53119;
$port = rand($startPort, $endPort);

if ($rs_tickets->rowCount() > 0){
	while ($ticket = $rs_tickets->fetch(PDO::FETCH_ASSOC)){

		// If there is a "cancelled" timestamp and it was more than 30 minutes ago, resubmit the cancel query (got missed/lost)
		if ($ticket['cancel_status'] > 0 && (time() - $ticket['cancel_status']) > 1800){
			$query = "cancel|".$ticket['ticket_key'];
		}
		$query = "status|".$ticket['ticket_key'];

//echo $query."\r\n";

		// Set the port number for the API call
		if ($port == $endPort){
			$port = $startPort;
		}else{
			$port++;
		}

		// Execute the query call (call myself on another port)
		exec('/var/www/html/batch/process-ecn-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

		// Wait a tick to give it time to finish getting the data
		//sleep(2);
	}
}

//echo "Tickets updated.\r\n";
?>
