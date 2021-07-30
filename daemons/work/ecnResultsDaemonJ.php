<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any ecn API calls submitted through the ecnTrade daemons
*/

// Tell me when things go sideways
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// A function that searches recursive arrays
function recursive_array_search($needle, $haystack){
	foreach($haystack as $key=>$value){
		$current_key = $key;
		if ($needle === $value OR (is_array($value) && recursive_array_search($needle, $value) !== false)){
			return $current_key;
		}
	}
	return false;
}

// OK, let's get going...

// Run forever
set_time_limit(0);

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define process and use to define directory for results
$process = "ecn";

// Define which API server instance we are running (folder, name)
//$aAPI = array('api','API');
$aAPI = array('api2','API2');

// Do this forever
while (true){

	// Define directory
	$directory = "/".$aAPI[0]."/".$process."_processing/";

	// Open the directory
	if ($dh = opendir($directory)){

		// Set a counter for how many files have been processed during this run.
		$files = 0;

		// Read in each file, one at a time
		// If we've processed 500 files, bail and start over (pick up where we left off) to clean out any cobwebs
		while (($filename = readdir($dh)) !== false && $files <= 500){

			// if it's really a directory, and not a file, skip it
			if (is_dir($directory.$filename)){
				continue;
			}

			// If it's a file with the word "_output" in it's name, process it
			if (is_file($directory.$filename) && strpos($filename, "_output")) {

				// Rename the output file
				$aFilename = explode("_", $filename);
				$newFilename = $aFilename[0]."_processing_".$aFilename[2]."_".$aFilename[3];
				rename($directory.$filename, $directory.$newFilename);
				$filename = $newFilename;

				// Increment counter
				$files++;

				// Check it's contents for any <error> tags - if so, log it and loop
				if (exec('grep '.escapeshellarg("<error>").' '.$directory.$filename)) {

					// Open it up
					$fh = fopen($directory.$filename, "r");

					// Rip it's guts out
					$contents = fgets($fh, filesize($directory.$filename)+1);

					// Sew it back up
					fclose($fh);

					$aContents = explode("</error>", $contents);
					$aError = explode("<error>", $aContents[0]);

					$query =	"INSERT INTO ".$fetch_errors_table." (
									timestamp,
									server,
									query,
									error,
									contents
								) VALUES (
									UNIX_TIMESTAMP(),
									:server,
									:query,
									:error,
									:contents
								)";
					try{
						$rsInsert = $mLink->prepare($query);
						$aValues = array(
							':server'		=> $aAPI[1],
						   	':query'		=> addslashes($aContents[1]),
						   	':error'		=> addslashes($aError[1]),
						   	':contents'		=> addslashes($contents)
						);
						$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
						//echo $preparedQuery."\n";
						$rsInsert->execute($aValues);
					}
					catch(PDOException $error){
						// Log any error
						file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}

					// Delete file
					unlink($directory.$filename);

					// Skip to the top (next file)
					continue;
				}

				// OK, not an error, parse it into an XML object so we can process it
				$xml = simplexml_load_file($directory.$filename);
print_r($xml);
die();
				// Grab the method
				$method	= $xml->method;

				// Process the results depending on the method specified
				switch ($method){

					//--------------------------------------------------
//					case "positionDetail":







							// ECN
							case "create":
								$ticketKey	= ${$aValues}[2]['value']; // returns the unique key assigned to the new ticket
								$ticketID	= ${$aValues}[3]['value']; // returns the passed ticket ID for identification
//echo $ticketKey."\r\n";
								// Update the already created new ticket with the returned ticketKey
								$query =	"UPDATE ".$fund_tickets_table."
											 SET	ticket_key	= '".$ticketKey."'
											 WHERE	ticket_id	= '".$ticketID."'
											";
//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "status":
								$ticketKey	= trim(${$aValues}[2]['value']);
								$opened		= trim(${$aValues}[3]['value']);
								$status		= trim(${$aValues}[4]['value']);
								$closed		= trim(${$aValues}[5]['value']);
								$net		= trim(${$aValues}[6]['value']);
								$commission	= trim(${$aValues}[7]['value']);
								$price		= trim(${$aValues}[8]['value']);
								$secFee		= trim(${$aValues}[9]['value']);
								$comment	= trim(${$aValues}[10]['value']);
								$sharesFilled	= trim(${$aValues}[11]['value']);

								// Update the ticket as closed, if it is.
								if ($status == "closed"){

									// See if this ticket was already closed (dupe status)
									$query =	"SELECT status
												 FROM ".$fund_tickets_table."
												 WHERE ticket_key = '".$ticketKey."'
												";
									$rs_status = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									$state = mysql_fetch_assoc($rs_status);

									// Bail if it's already closed
									if (trim($state['status']) == "closed"){
										break;
									}

									$query =	"UPDATE ".$fund_tickets_table."
												 SET	status		= '".$status."',
														closed		= ".strtotime($closed).",
														net			= ".$net.",
														commission	= ".$commission.",
														price		= ".$price.",
														secFee		= ".$secFee.",
														comment		= '".addslashes($comment)."',
														sharesFilled = ".$sharesFilled."
												 WHERE	ticket_key	= '".$ticketKey."'
												";
//echo $query."\r\n";
									$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!")

									// Get info about the ticket for the notification
									$query =	"SELECT *
												 FROM ".$fund_tickets_table."
												 WHERE ticket_key = '".$ticketKey."'
												";
									$rs_ticket = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									$ticket = mysql_fetch_assoc($rs_ticket);

									// See if this trade was already inserted by the tradesForPosition or tradesForPosition methods (rare)
									$query =	"SELECT ticket_key
												 FROM ".$fund_trades_table."
												 WHERE ticket_key = '".$ticketKey."'
												";
									$rs_status = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									if (mysql_num_rows($rs_status) < 1){

										// Insert the trade record
										$query = "
											INSERT INTO ".$fund_trades_table." (
												fund_id,
												timestamp,
												trans_id,
												company_id,
												stockSymbol,
												opened,
												unix_opened,
												closed,
												unix_closed,
												shares,
												sharesOrdered,
												sharesFilled,
												price,
												`limit`,
												dayOrGTC,
												ticketKey,
												ticketStatus,
												createdByCA,
												net,
												secFee,
												commission,
												buyOrSell
											) VALUE (
												'".$ticket["fund_id"]."',
												UNIX_TIMESTAMP(),
												".$ticket["ticket_id"].",
												0,
												'".$ticket["symbol"]."',
												'".date('Ymd', $ticket["openned"])."',
												".$ticket["openned"].",
												'".date('Ymd', $ticket["closed"])."',
												".$ticket["closed"].",
												NULL,
												".$ticket["shares"].",
												".$ticket["sharesFilled"].",
												".$ticket["price"].",
												".$ticket["limit"].",
												'".$ticket["type"]."',
												'".$ticket["ticket_key"]."',
												'".$ticket["status"]."',
												0,
												".$ticket["net"].",
												".$ticket["secFee"].",
												".$ticket["commission"].",
												'".$ticket["action"]."'
											)
										";
										//echo $query."\r\n";
										$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									}

									// Set the currency format for the notification
									setlocale(LC_MONETARY, 'en_US');

									// Get the fund symbol for the notification
									$query =	"SELECT fund_symbol
												 FROM ".$fund_table ."
												 WHERE fund_id = '".$ticket["fund_id"]."'
												";
									$rs_symbol = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									$symbol = mysql_fetch_assoc($rs_symbol);

									//Define the notification values
									$notificationID = "02-001";
									$memberID	= $ticket["member_id"];//memberID

									//Custom notification
									$notification = "Your ".strtoupper($ticket["action"])." order for ".number_format($ticket["shares"])." shares of ".$ticket["symbol"]." for your ".$symbol["fund_symbol"]." fund has closed at ".money_format('%.2n', $ticket["price"]).".";
									$link	= "?page=02-00-00-003";


									// Insert the notification
									$query = "
										INSERT INTO members_notifications (
											notification_id,
											member_id,
											notification,
											link,
											timestamp
										) VALUE (
											'".$notificationID."',
											".$memberID.",
											'".$notification."',
											'".$link."',
											UNIX_TIMESTAMP()
										)
									";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// Update their stratification
//									exec('/usr/bin/php /var/www/html/portfolio.marketocracy.com/scripts/strat-build.php "fundID='.$ticket["fund_id"].'" > /dev/null &');
									exec('/usr/bin/php /var/www/html/scripts/strat-build.php "fundID='.$ticket["fund_id"].'" > /dev/null &');

								}elseif ($status == "open"){
									$query =	"UPDATE ".$fund_tickets_table."
												 SET	sharesFilled = ".$sharesFilled."
												 WHERE	ticket_key	= '".$ticketKey."'
												";
//echo $query."\r\n";
 									$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!")

								}
								//echo "Success";
								break;


							case "cancel":
								$cancelled	= ${$aValues}[2]['value']; // returns "success" is successfully cancelled, "fail" if already closed/cancelled
								$ticketKey	= ${$aValues}[3]['value'];
//echo $cancelled."\r\n";
								// Update the ticket as cancelled, if it is.
								if ($cancelled == "success"){
									$query =	"UPDATE ".$fund_tickets_table."
												 SET	status		= 'cancelled',
														closed		= UNIX_TIMESTAMP()
												 WHERE	ticket_key	= '".$ticketKey."'
												";
//echo $query."\r\n";
									$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;

/* No longer works after splitting the processes
							case "close":
								// Just close it.  Similar to cancelling, but flagging it with a status of "closed" in case the actual closing was missed.
								$query =	"UPDATE ".$fund_tickets_table."
											 SET	status		= 'closed',
													closed		= UNIX_TIMESTAMP()
											 WHERE	ticket_key	= '".$ticketKey."'
											";
//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;
*/

						}
					}
					// archive or delete the file
//					if ($aProcess[$folder] == "ecn"){
						rename($directory.$filename, $directory."history/".$filename);
//					}else{
//						unlink($directory.$filename);
//					}
				}
			}
			// Close up the directory
			closedir($dh);
//die();
		}
	}
break;
}
?>