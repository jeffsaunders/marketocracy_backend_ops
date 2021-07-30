<?php
// This commandline batch script checks for any open trade tickets and queries their status, updating that status (mostly closing) if it has changed.
// By running it as a CRON you can set the checking and updating interval
// Example:
//	*/5 * * * * /usr/bin/php /var/www/html/batch/getTicketStatus.php
// *Note - this will not run within a web browser.

// Tell me when things go sideways
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Start me up
//session_start();

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

// Set some values
$api = "API2";
if ($api == "API1"){
	$startPort = 53000;
	$endPort = 53019;
}else{
	$startPort = 53100;
	$endPort = 53119;
}

// See if the ECN queue is empty
$query = "
	SELECT ecn_processing
	FROM ".$api_queue_table."
	WHERE api = '".$api."'
";
try{
	$rs_queue = $mLink->prepare($query);
	$rs_queue->execute();
}
catch(PDOException $error){
// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
$queue = $rs_queue->fetch(PDO::FETCH_ASSOC);
$aValues = explode("|", $queue['ecn_processing']);

if ($aValues[0] > 0){

	// Don't run if we're still processing the last run
	die();

}else{

	// Check for open tickets
//	if (isMarketOpen(time(), $mLink, "after")){ // Do this only if the markets are open (and for half an hour after closing)
        if (isMarketOpen(time(), $mLink, "indices")){ // Do this only if the markets are open (and for 2 hours after closing)

// Run anytime
//if (1 == 1){
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

		// Only grab unclosed tickets marked for cancellation
		$query = "
			SELECT ticket_key, cancel_status
			FROM ".$fund_tickets_table."
			WHERE (status = 'pending'
				   OR status = 'open'
			)
			AND cancel_status <> 0
			AND ticket_key <> ''
		";
	}

	try{
		$rs_tickets = $mLink->prepare($query);
		$rs_tickets->execute();
	}
	catch(PDOException $error){
	// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Randomly pick a starting port
	$port = rand($startPort, $endPort);

	if ($rs_tickets->rowCount() > 0){
		while ($ticket = $rs_tickets->fetch(PDO::FETCH_ASSOC)){

                        // If there is a "cancelled" timestamp and it was more than 30 minutes ago, resubmit the cancel query (it got missed/lost)
                        // Should then be closed upon the next status check
                        if ($ticket['cancel_status'] > 0 && (time() - $ticket['cancel_status']) > 1800){

                                $query = "cancel|".$ticket['ticket_key'];
                                //echo $query."\r\n";

                                // Set the port number for the API call
//                                if ($port == $endPort){
//                                        $port = $startPort;
//                                }else{
//                                        $port++;
//                                }
			        $port = rand(53100, 53119);

                                // Execute the query call (call myself on another port)
                                exec('/var/www/html/batch/process-ecn-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

                                // Wait a few ticks to give it time to finish getting the data
//                                sleep(5);

//				continue;

                        }

			// If a ticket looks clean, check it's status
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
			//sleep(1);
			usleep(250000);

		}
	}
}

//echo "Tickets updated.\r\n";
?>
