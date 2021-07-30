<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any API call submitted through the ecn daemon
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
//require("../includes/dbPConnect.php");
require("/var/www/html/includes/dbPConnect.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfig.php");

// Define processes and use to define directories for results
//$aProcess = array('fundprice','stockprice','ecn','manageradmin','ranking','trade');
//$aProcess = array('fundprice','stockprice','ecn','manageradmin','trade');
//$aProcess = array('fundprice','stockprice','ecn','manageradmin');
$aProcess = array('ecn');

// Define which API server instance we are running (folder, name)
$aAPI = array('api','API');

// Do this forever
while (true){
	// Check each directory
	for ($folder = 0; $folder < sizeof($aProcess); $folder++){
		$directory = "/".$aAPI[0]."/".$aProcess[$folder]."_processing/";
//echo $directory."\n";
		// Open the directory
		if ($dh = opendir($directory)){
		// Read in each file, one at a time

//----------------------------ADD A COUNTER HERE TO READ IN ONLY THAT MANY, THEN BAIL, SO IT CLEANS UP BETWEEN BLOCKS
$files = 1;
			while (($filename = readdir($dh)) !== false && $files <= 5000){
				// if it's really a directory, skip it
				if (is_dir($directory.$filename)){
					continue;
				}
				// If it's a file with the word "_output" in it's name, process it
				if (is_file($directory.$filename) && strpos($filename, "_output")) {
$files++;
//echo $filename."\n";
					// Open it up
//exec('cp '.$directory.$filename.' '.$directory.'/test_files/'.$filename);
					$fh = fopen($directory.$filename, "r");

					// Rip it's guts out
					$contents = fgets($fh, filesize($directory.$filename)+1);
//echo $contents."\n";
					// Sew it back up
					fclose($fh);

					// Create a VERY random unique name for the results array
					$aValues = "aValues".time().rand(0, 65535);

					// Parse the contents XML for DB insertion
					$parser = xml_parser_create();
					xml_parse_into_struct($parser, $contents, $$aValues);
					xml_parser_free($parser);
//print_r($$aValues);
					// Pull the method value out of the results so it's not part of the insert/update query
					$method = ${$aValues}[recursive_array_search('METHOD', $$aValues)]['value'];
					unset(${$aValues}[recursive_array_search('METHOD', $$aValues)]);
//echo $method."\n";
					// Process the results
					if (${$aValues}[0]['tag'] != "RESULTS"){  // If it's not "RESULTS" then it's not valid data

						$aContents = explode("</error>", $contents);
						$aError = explode("<error>", $aContents[0]);

						//Write error message to system_fetch_errors
//						$query =	"INSERT INTO ".$fetch_errors_table." (
//										timestamp,
//										input,
//										query,
//										error
//									) VALUES (
//										UNIX_TIMESTAMP(),
//										'".addslashes($input)."',
//										'".addslashes($url)."',
//										'".addslashes($output)."'
//									)";

						// under the new design there isn't much to log except what we got back
						$query =	"INSERT INTO ".$fetch_errors_table." (
										timestamp,
										server,
										query,
										error,
										contents
									) VALUES (
										UNIX_TIMESTAMP(),
										'".$aAPI[1]."',
										'".addslashes($aContents[1])."',
										'".addslashes($aError[1])."',
										'".addslashes($contents)."'
									)";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						//echo "Aborted - Error logged";
					}else{ // No errors, process the results
						// Process the results depending on the method specified
						switch ($method){

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

									// Get member info for livePrice call and the notification
									$query =	"SELECT m.username, f.fund_symbol
												 FROM ".$members_table." m, ".$fund_table." f
												 WHERE fund_id = '".$ticket["fund_id"]."'
												 AND m.member_id = m.member_id
												";
									$rs_member = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									$member = mysql_fetch_assoc($rs_member);


									// Force update of the livePrice values
									$query = "livePrice|".$member["username"]."|".$ticket["fund_id"]."|".$member["fund_symbol"].";

									// Set the port number for the API call
									$port = rand(52000, 52099);

									// Execute the query call to submit the request
									exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');






									// Set the currency format for the notification
									setlocale(LC_MONETARY, 'en_US');

									// Get the fund symbol for the notification
//									$query =	"SELECT fund_symbol
//												 FROM ".$fund_table ."
//												 WHERE fund_id = '".$ticket["fund_id"]."'
//												";
//									$rs_symbol = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//									$symbol = mysql_fetch_assoc($rs_symbol);

									//Define the notification values
									$notificationID = "02-001";
									$memberID	= $ticket["member_id"];//memberID

									//Custom notification
									$notification = "Your ".strtoupper($ticket["action"])." order for ".number_format($ticket["shares"])." shares of ".$ticket["symbol"]." for your ".$member["fund_symbol"]." fund has closed at ".money_format('%.2n', $ticket["price"]).".";
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