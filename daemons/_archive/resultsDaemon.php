<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any API call submitted through the legacyData or ecn daemons
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
$aProcess = array('fundprice','stockprice','ecn','manageradmin','trade');
//$aProcess = array('fundprice','stockprice','ecn','manageradmin');
//$aProcess = array('manageradmin');

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
			while (($filename = readdir($dh)) !== false && $files <= 500){
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

									// Insert the trade record
									$query = "
										INSERT INTO ".$fund_trades_table." (
											fund_id,
											timestamp,
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

									// Set the currency format for the notification
									setlocale(LC_MONETARY, 'en_US');

									//Define the notification values
									$notificationID = "02-001";
									$memberID	= $ticket["member_id"];//memberID

									//Custom notification
									$notification = "Your ".strtoupper($ticket["action"])." order for ".number_format($ticket["shares"])." shares of ".$ticket["symbol"]." has closed at ".money_format('%.2n', $ticket["price"]).".";
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

							// Membership
							case "newManager":
								$memberID		= ${$aValues}[2]['value']; // returns the member_id passed to the API
//echo $memberID."\r\n";
								$primaryKey		= ${$aValues}[3]['value']; // returns the unique key assigned to the new member
//echo $primaryKey."\r\n";
								$portfolioKey	= ${$aValues}[4]['value']; // returns the new first portfolio key assigned to the new member
//echo $portfolioKey."\r\n";
								// Now insert the key into the new member's membership record
								$query =	"UPDATE ".$members_table."
											 SET	fb_primarykey = 'X\'".$primaryKey."\'',
											 		fb_portfoliokey = 'X\'".$portfolioKey."\''
											 WHERE member_id = ".$memberID
											;
								//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "newFund":
								$primaryKey = ${$aValues}[2]['value']; // returns the unique key assigned to the new fund
								$fundID = ${$aValues}[3]['value']; // returns the fund_id passed to the API
								// Now insert the key into the member's fund record
								$query =	"UPDATE ".$fund_table."
											 SET fb_primarykey = 'X\'".$primaryKey."\''
											 WHERE fund_id = '".$fundID."'
											";
								//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "managerPassword":
								$username = ${$aValues}[2]['value']; // returns the members login name
								$password = ${$aValues}[3]['value']; // returns the members clear password
								// Now update the member's password
								$query =	"UPDATE clear_passwords
											 SET password = '".$password."'
											 WHERE username = '".$username."'
											";
								//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								//if the update failed they aren;t already in the table, so insert them instead
								if (!mysql_affected_rows()){

									$query = "
										INSERT INTO clear_passwords (
											username,
											password
										) VALUE (
											'".$username."',
											'".$password."'
										)
									";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "updateSymbol":
								// Nothing to do...
								//echo "Success";
								break;


							case "updateName":
								// Nothing to do...
								//echo "Success";
								break;


							// Fund Info
							case "maxDate":
//								$maxDate = ${$aValues}[1]['value']; // returns YYYYMMDD
								$maxDate = ${$aValues}[recursive_array_search('DATE', $$aValues)]['value'];
//echo $maxDate."\r\n";
								if ($maxDate != ""){
									// Write maxDate to members_fund_maxdate (overwrite old value)
									$query =	"UPDATE ".$fund_maxdate_table."
												 SET timestamp = UNIX_TIMESTAMP(),
												 maxdate = '".$maxDate."'
												 WHERE 1
												";
									//echo $query."\r\n";
									$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "priceManager":
								$priceManager = ${$aValues}[1]['value']; // returns "sent" if successful
								// Now do something with it...
								// Not sure what to do here, if anything.........
//echo $priceManager."\r\n";
								//echo "Success";
								break;


							case "livePrice":
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

										// Create VERY unique names for the two placeholder arrays
										$aTag = "aTag".time().rand(0, 65535);
										$aVal = "aVal".time().rand(0, 65535);

										// Initialize arrays
										$$aTag = array();
										$$aVal = array();
									}
									if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push($$aTag, ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
										if (strpos($value, "e")){ // Exponential number
											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
										}
										// Push the value onto the values array
										array_push($$aVal, $value);
										if (${$aValues}[$element]['tag'] == "FUND_ID"){
											// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
											// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
											$fundID = trim(''.$value);
										}
									}
								}
								// Check to see if the fund is already in the table
								$query =	"SELECT *
											 FROM ".$fund_liveprice_table."
											 WHERE fund_id = '".$fundID."'
											";
								$rs_select = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								// WOW!  It isn't (SURPRISE!)  Let's add it.
								if (mysql_num_rows($rs_select) == 0){
									$query = "INSERT INTO ".$fund_liveprice_table." (timestamp, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
										$query .= ${$aTag}[$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES (UNIX_TIMESTAMP(), ";
									// Tack on all the values
									for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
										if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
											$query .= "'".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
									// Do it!
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}else{  // It is there, just update it
									$query = "UPDATE ".$fund_liveprice_table." SET timestamp = UNIX_TIMESTAMP(), ";

									for ($element = 0; $element < sizeof($$aTag); $element++){
										if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
											$query .= ${$aTag}[$element]." = '".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= ${$aTag}[$element]." = NULL, ";
											}else{
												$query .= ${$aTag}[$element]." = ".${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= " WHERE fund_id = '".$fundID."'";
									//echo $query."\r\n";
									$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "priceRun":
								$day = 0; // In case there is more than one day

								// Create VERY unique names for the two placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
										$fundID = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 2){ // About to start data

										// Initialize arrays
										${$aTag}[$day] = array();
										${$aVal}[$day] = array();
									}
									if (${$aValues}[$element]['level'] == 3){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push(${$aTag}[$day], ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
										if (strpos($value, "e")){ // Exponential number
											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
										}
										// Push the value onto the values array
										array_push(${$aVal}[$day], $value);
									}
									if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 2){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\r\n\n";
										// Increment the day counter
										$day++;
									}
								}
//print_r($$aVal);
								// Now build the query to insert the data
								for ($row = 0; $row < sizeof($$aTag); $row++){
									$query = "INSERT INTO ".$fund_pricing_table." (fund_id, timestamp, unix_date, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
										$query .= ${$aTag}[$row][$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[$row][0], 4, 2), substr(${$aVal}[$row][0], 6, 2), substr(${$aVal}[$row][0], 0, 4)).", ";
									// Tack on all the values
									for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
										$query .= ${$aVal}[$row][$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
//echo $query."\r\n";
									// Do it!
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "aggregate": // JIC the method is shortened somewhere, as it was in the beginning
							case "aggregateStatistics":
								$history = false;
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

										// Create VERY unique names for the two placeholder arrays
										$aTag = "aTag".time().rand(0, 65535);
										$aVal = "aVal".time().rand(0, 65535);

										// Initialize arrays
										$$aTag = array();
										$$aVal = array();
									}
									if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push($$aTag, ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
										if (strpos($value, "e")){ // Exponential number
											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
										}
										// Push the value onto the values array
										array_push($$aVal, $value);
										if (${$aValues}[$element]['tag'] == "FUND_ID"){
											// Look for a "History Flag" attached to the fund_id.  If it's there, set the val then remove the flag
											if (substr($value, -1) == "H"){
												$history = true;
												$fundID = substr($value, 0, -1);
											}else{
												// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
												// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
												$fundID = trim(''.$value);
											}
										}
									}
								}

								// If this is for history, build the query for it
								if ($history){
									// Build the query to insert the data inot the history table
									$query = "INSERT INTO ".$fund_aggregate_history_table." (timestamp, unix_date, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
										$query .= ${$aTag}[$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
									// Tack on all the values
									for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
										if (strpos(${$aTag}[$element], "UND_ID")){ // Strip trailing "H" off ID and add quotes
											$query .= "'".substr(${$aVal}[$element], 0, -1)."', ";
										}else if (strpos(${$aTag}[$element], "DATE")){ // Date fields, add quotes
											$query .= "'".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
								}else{ // Not history
									// Delete all the existing records - we are replacing them with fresh ones
									$query = "	DELETE FROM ".$fund_aggregate_table."
												WHERE fund_id = '".$fundID."'
									";
									$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// Now build the query to insert the data
									$query = "INSERT INTO ".$fund_aggregate_table." (timestamp, unix_date, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof($$aTag); $element++){
	//echo $aTag[$element]."\r\n";
										$query .= ${$aTag}[$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
									// Tack on all the values
									for ($element = 0; $element < sizeof($$aVal); $element++){
	//echo $aVal[$element]."\r\n";
										if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
											$query .= "'".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
								}
								// Do it!
								$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "alphaBeta": // JIC the method is shortened
							case "alphaBetaStatistics":
								$history = false;
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

										// Create VERY unique names for the two placeholder arrays
										$aTag = "aTag".time().rand(0, 65535);
										$aVal = "aVal".time().rand(0, 65535);

										// Initialize arrays
										$$aTag = array();
										$$aVal = array();
									}
									if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push($$aTag, ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
										if (strpos($value, "e")){ // Exponential number
											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
										}
										// Push the value onto the values array
										array_push($$aVal, $value);
										if (${$aValues}[$element]['tag'] == "FUND_ID"){
											// Look for a "History Flag" attached to the fund_id.  If it's there, set the vat then remove the flag
											if (substr($value, -1) == "H"){
												$history = true;
												$fundID = substr($value, 0, -1);
											}else{
												// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
												// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
												$fundID = trim(''.$value);
											}
										}
									}
								}

								// If this is for history, build the query for it
								if ($history){
									// Build the query to insert the data into the history table
									$query = "INSERT INTO ".$fund_alphabeta_history_table." (timestamp, unix_date, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
										$query .= ${$aTag}[$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
									// Tack on all the values
									for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
										if (strpos(${$aTag}[$element], "UND_ID")){ // Strip trailing "H" off ID and add quotes
											$query .= "'".substr(${$aVal}[$element], 0, -1)."', ";
										}else if (strpos(${$aTag}[$element], "DATE")){ // Date fields, add quotes
											$query .= "'".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
								}else{ // Not history
									// Delete all the existing records - we are replacing them with fresh ones
									$query = "	DELETE FROM ".$fund_alphabeta_table."
												WHERE fund_id = '".$fundID."'
									";
									$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// Now build the query to insert the data
									$query = "INSERT INTO ".$fund_alphabeta_table." (timestamp, unix_date, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
										$query .= ${$aTag}[$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
									// Tack on all the values
									for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
										if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
											$query .= "'".${$aVal}[$element]."', ";
										}else{
											if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
								}
								// Do it!
								$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "positionDetail":
								// This method actually writes to the members_fund_positions table (not _details), despite the naming confusion
								$stock = 0;

								// Create VERY unique names for the two placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['tag'] == "DATE" && ${$aValues}[$element]['level'] == 2){
										$date = ${$aValues}[$element]['value'];
									}
									if (${$aValues}[$element]['tag'] == "FUND_ID"){
										$fundID = ${$aValues}[$element]['value'];
									}
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 3){ // About to start data

										// Initialize arrays
										${$aTag}[$stock] = array();
										${$aVal}[$stock] = array();
									}
									if (${$aValues}[$element]['level'] == 4){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push(${$aTag}[$stock], ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
										// Push the value onto the values array
										array_push(${$aVal}[$stock], $value);
									}
									if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 3){ // Passed end of data
//print_r($aTag[$stock]);
//print_r($aVal[$stock]);
//echo "\r\n\n";
										$stock++;
									}
								}

								// If there are no positions, write a dummy record that says so and bail
								if (sizeof($$aTag) < 1){
									$query =	"INSERT INTO ".$fund_positions_table." (
												fund_id,
												timestamp,
												unix_date,
												date,
												company_id,
												stockSymbol,
												name
											) VALUES (
												'".$fundID."',
												UNIX_TIMESTAMP(),
												".mktime(0, 0, 0, substr($date, 4, 2), substr($date, 6, 2), substr($date, 0, 4)).",
												".$date.",
												'-1',
												'99999',
												'No Positions'
											)";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									break;
								}

								// Delete any existing records for this fund on this date - we are replacing them with fresh ones
								$query = "	DELETE FROM ".$fund_positions_table."
											WHERE fund_id = '".$fundID."'
											AND date = '".$date."'
								";
								$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Now build the query to insert the data
								for ($row = 0; $row < sizeof($$aTag); $row++){

									// Get the company ID so we can store it with each record
									$stockSymbol = trim(''.${$aVal}[$row][0]);
									$query = "	SELECT company_id
												FROM ".$stock_prices_table."
												WHERE symbol = '".$stockSymbol."'
												ORDER BY timestamp DESC
												LIMIT 1
									";
									$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// If the stock is missing, go get it - rename these results and fire off a request for the missing stockInfo
									// By doing this we just keep passing on this one until the requested stockInfo is returned, then we can finally process it
/*
									if (mysql_num_rows($rs_companyID) == 0){

										// Only do this if we haven't already
										if (!strpos($filename, "_stockInfo")){

											$query = "stockInfo|".$stockSymbol;
											//echo $query."\n";

											// Set the port number for the API call
											$port = rand(52100, 52499);

											// Execute the query call to submit the request
											exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

											// tack "_stockInfo" to the end of this output filename so we flag it
											rename($directory.$filename, $directory.$filename."_stockInfo");
										}
										// Bail entirely on this output file and move on to the next one
										continue 2;
									}
									$companyInfo = mysql_fetch_assoc($rs_companyID);
									$companyID = $companyInfo['company_id'];
*/
									if (mysql_num_rows($rs_companyID) == 0){
										$companyID = 0;
									}else{
										$companyInfo = mysql_fetch_assoc($rs_companyID);
										$companyID = $companyInfo['company_id'];
									}
//echo $companyID."\n";
									// Now build the query to insert the data
									$query = "INSERT INTO ".$fund_positions_table." (fund_id, timestamp, unix_date, date, company_id, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
										$query .= ${$aTag}[$row][$element].", ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($date, 4, 2), substr($date, 6, 2), substr($date, 0, 4)).", ".$date.", '".$companyID."', ";
									// Tack on all the values
									for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
										if (strpos(${$aTag}[$row][$element], "DATE") || ${$aTag}[$row][$element] == "STOCKSYMBOL" || ${$aTag}[$row][$element] == "NAME"){ // Character fields, add quotes
											$query .= "'".${$aVal}[$row][$element]."', ";
										}else{
											if (${$aVal}[$row][$element] == ''){ // Blank decimal value, set it to NULL
												$query .= "NULL, ";
											}else{
												$query .= ${$aVal}[$row][$element].", ";
											}
										}
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
									// Do it!
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "positionInfo":
								// This method actually writes to the members_fund_positions_details table, despite the naming confusion
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

										// Create VERY unique names for the placeholder arrays
										$aTag = "aTag".time().rand(0, 65535);
										$aVal = "aVal".time().rand(0, 65535);

										// Initialize arrays
										$$aTag = array();
										$$aVal = array();
									}
									if (${$aValues}[$element]['level'] == 2){
										if (${$aValues}[$element]['tag'] == "DIVIDENDS" || ${$aValues}[$element]['tag'] == "SHARECHANGEACTIONS"){
											continue;
										}else{ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
											// Push the tag name onto the tags array
											array_push($$aTag, ${$aValues}[$element]['tag']);
											$value = addslashes(${$aValues}[$element]['value']);
//											if (strpos($value, "e")){ // Exponential number
//												$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//											}
											// Push the value onto the values array
											array_push($$aVal, $value);
											if (${$aValues}[$element]['tag'] == "FUND_ID"){
												// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
												// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
												$fundID = trim(''.$value);
											}
											if (${$aValues}[$element]['tag'] == "STOCKSYMBOL"){ // Returned Stock Symbol
												$stockSymbol = trim(''.$value);

												// Get the company_id for this symbol for CA/Dividend records
												$query = "	SELECT company_id
															FROM ".$stock_prices_table."
															WHERE symbol = '".$stockSymbol."'
															ORDER BY timestamp DESC
															LIMIT 1
												";
												$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!"
												$companyInfo = mysql_fetch_assoc($rs_companyID);
												$companyID = $companyInfo['company_id'];

												// Delete all the existing dividend records - we are replacing them with fresh ones
												$query = "	DELETE FROM ".$stock_dividends_table."
															WHERE company_id = '".$companyID."'
															AND symbol = '".$stockSymbol."'
												";
//												$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

												// Delete all the existing changeaction records - we are replacing them with fresh ones
												$query = "	DELETE FROM ".$stock_changeactions_table."
															WHERE company_id = '".$companyID."'
															AND symbol = '".$stockSymbol."'
												";
//												$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

											}
										}
									}

									// If we hit a dividend or a changeaction
									if (${$aValues}[$element]['level'] == 3){
										// Close tag, loop
										if (${$aValues}[$element]['type'] == "close"){
											continue;
										}else{
											// Process a dividend
											if (${$aValues}[$element]['tag'] == "ONEDIVIDEND" && ${$aValues}[$element]['type'] == "open"){

												// Initialize arrays
												$aDTag = array();
												$aDVal = array();

												// Stuff the tags and values
												for ($div = 0; $div < 4; $div++){
													$element++;
						  							array_push($aDTag, ${$aValues}[$element]['tag']);
													$value = addslashes(${$aValues}[$element]['value']);
													array_push($aDVal, $value);
												}
//print_r($aDTag);print_r($aDVal);
												// Now build the query to insert the data
												$query = "INSERT INTO ".$stock_dividends_table." (company_id, symbol, timestamp, ed_timestamp, rd_timestamp, pd_timestamp, ";

												// Tack on all the tag names
												for ($elementD = 0; $elementD < sizeof($aDTag); $elementD++){
													$query .= $aDTag[$elementD].", ";
												}

												// Pop the trailing ", " off
												$query = substr($query, 0, -2);

												// Now add the values
												$query .= ") VALUES ('".$companyID."', '".$stockSymbol."', UNIX_TIMESTAMP(), ".strtotime($aDVal[0]).", ".strtotime($aDVal[1]).", ".strtotime($aDVal[2]).", "; // 0, 1, & 2 are effectiveDate, recordDate, & payDate resp.

												// Tack on all the values
												for ($elementD = 0; $elementD < sizeof($aDVal); $elementD++){
													$query .= "'".$aDVal[$elementD]."', ";
												}

												// Pop the trailing ", " off and close 'er up
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";

												// Insert it
//												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

											// Process a changeaction (CA)
											}else if (${$aValues}[$element]['tag'] == "ONEACTION" && ${$aValues}[$element]['type'] == "open"){

												// Initialize arrays
												$aCTag = array();
												$aCVal = array();

												// Stuff the tags and values
												for ($div = 0; $div < 2; $div++){
													$element++;
						  							array_push($aCTag, ${$aValues}[$element]['tag']);
													$value = addslashes(${$aValues}[$element]['value']);
													array_push($aCVal, $value);
												}
//print_r($aCTag);print_r($aCVal);
												// Now build the query to insert the data
												$query = "INSERT INTO ".$stock_changeactions_table." (company_id, symbol, timestamp, ed_timestamp, ";

												// Tack on all the tag names
												for ($elementC = 0; $elementC < sizeof($aCTag); $elementC++){
													$query .= $aCTag[$elementC].", ";
												}

												// Pop the trailing ", " off
												$query = substr($query, 0, -2);

												// Now add the values
												$query .= ") VALUES ('".$companyID."', '".$stockSymbol."', UNIX_TIMESTAMP(), ".strtotime($aCVal[0]).", "; // 0 is effectiveDate

												// Tack on all the values
												for ($elementC = 0; $elementC < sizeof($aCVal); $elementC++){
													$query .= "'".$aCVal[$elementC]."', ";
												}

												// Pop the trailing ", " off and close 'er up
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";

												// Insert it
//												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
											}
										}
									}
								}
//print_r($$aTag);print_r($$aVal);//die();
								// Delete all the existing records - we are replacing them with fresh ones
								$query = "	DELETE FROM ".$fund_positions_details_table."
											WHERE fund_id = '".$fundID."'
											AND stockSymbol = '".$stockSymbol."'
								";
								$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Now build the query to insert the data
								$query = "INSERT INTO ".$fund_positions_details_table." (timestamp, first_trade_unix_date, last_trade_unix_date, ";

								// Tack on all the tag names
								for ($element = 0; $element < sizeof($$aTag); $element++){
									$query .= ${$aTag}[$element].", ";
								}

								// Pop the trailing ", " off
								$query = substr($query, 0, -2);

								// Now add the values
								$query .= ") VALUES (UNIX_TIMESTAMP(), ".strtotime(${$aVal}[16]).", ".strtotime(${$aVal}[17]).", "; // 16 & 17 are firstTradeTimestamp and lastTradeTimestamp resp.

								// Tack on all the values
								for ($element = 0; $element < sizeof($$aVal); $element++){
									if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
										$query .= "NULL, ";
									}else{
										$query .= "'".${$aVal}[$element]."', ";
									}
								}

								// Pop the trailing ", " off and close 'er up
								$query = substr($query, 0, -2);
								$query .= ")";
								//echo $query."\r\n";

								// Do it!
								$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "allPositionInfo":
								// This method actually writes to the members_fund_positions_details table, despite the naming confusion

								// Create VERY unique names for the placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['level'] == 2){
										// Close tag, loop
										if (${$aValues}[$element]['type'] == "close"){
											continue;
										}else{
											// Grab fund_id
											if (${$aValues}[$element]['tag'] == "FUND_ID"){
												$value = addslashes(${$aValues}[$element]['value']);
												// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
												// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
												$fundID = trim(''.$value);
											}
										}
									}

									if (${$aValues}[$element]['tag'] == "POSITION" && ${$aValues}[$element]['type'] == "open"){

										// (re)Initialize arrays
										$$aTag = array();
										$$aVal = array();

										// Flag signaling whether to save the results or not when the time comes
										$savePosition = false;

									}
									if (${$aValues}[$element]['level'] == 4){
										// Skip dividends and CAs
										if (${$aValues}[$element]['tag'] == "DIVIDENDS" || ${$aValues}[$element]['tag'] == "SHARECHANGEACTIONS"){
											continue;
										}else{ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";

											// Flag signaling whether to save the results or not when the time comes
											// We are building the array of values so we want to save them
											$savePosition = true;

											// Push the tag name onto the tags array
											array_push($$aTag, ${$aValues}[$element]['tag']);
											$value = addslashes(${$aValues}[$element]['value']);
//											if (strpos($value, "e")){ // Exponential number
//												$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//											}

											// Push the value onto the values array
											array_push($$aVal, $value);
											if (${$aValues}[$element]['tag'] == "STOCKSYMBOL"){ // Returned Stock Symbol
												$stockSymbol = trim(''.$value);

												// Get the company_id for this symbol for CA/Dividend records
												$query = "	SELECT company_id
															FROM ".$stock_prices_table."
															WHERE symbol = '".$stockSymbol."'
															ORDER BY timestamp DESC
															LIMIT 1
												";
												$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!"
												$companyInfo = mysql_fetch_assoc($rs_companyID);
												$companyID = $companyInfo['company_id'];

												// Delete all the existing dividend records - we are replacing them with fresh ones (later on)
												// NO LONGER DONE HERE BUT REMOVING THIS CODE BREAKS THE PROCESS SO GO THROUGH THE MOTIONS, FOR NOW.
												$query = "	DELETE FROM ".$stock_dividends_table."
															WHERE company_id = '".$companyID."'
															AND symbol = '".$stockSymbol."'
												";
/////												$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

												// Delete all the existing changeaction records - we are replacing them with fresh ones (later on)
												// NO LONGER DONE HERE BUT REMOVING THIS CODE BREAKS THE PROCESS SO GO THROUGH THE MOTIONS, FOR NOW.
												$query = "	DELETE FROM ".$stock_changeactions_table."
															WHERE company_id = '".$companyID."'
															AND symbol = '".$stockSymbol."'
												";
/////												$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
											}
										}
									}

									// Reached the end of the position info
//									if ($savePosition && (${$aValues}[$element]['level'] == 5)){
									if ($savePosition && (${$aValues}[$element]['level'] == 5 || ${$aValues}[$element]['level'] == 3)){

										// Delete all the existing records - we are replacing them with fresh ones
										// SWITCH TO AN UPDATE METAPHOR SO DON'T DELETE ANYTHING
//										$query = "	DELETE FROM ".$fund_positions_details_table."
//													WHERE fund_id = '".$fundID."'
//													AND stockSymbol = '".$stockSymbol."'
//										";
//										$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// Update the position details
										$query = "	UPDATE ".$fund_positions_details_table."
													SET	fund_id	= '".$fundID."',
														timestamp = UNIX_TIMESTAMP(),
														first_trade_unix_date = ".strtotime(${$aVal}[15]).",
														last_trade_unix_date = ".strtotime(${$aVal}[16]).",
										";

										// Now add the variable ones based on the XML tags and values
										for ($tag = 0; $tag < sizeof($$aTag); $tag++){
											if (${$aVal}[$tag] == ''){ // Blank decimal value, set it to NULL
												$value = "NULL";
											}else{
												$value = "'".${$aVal}[$tag]."'";
											}
											$query .= ${$aTag}[$tag]." = ".$value.", ";
											if (${$aTag}[$row][$element] == "STOCKSYMBOL"){
												$stockSymbol = $value;
											}
										}

										// Pop the trailing ", " off
										$query = substr($query, 0, -2);

										// Where clause
										$query .= " WHERE fund_id = '".$fundID."'";
										$query .= " AND stockSymbol = '".$stockSymbol."'";

										echo $query."\r\n";
										// Do it!
										$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// Make sure it updated, if not insert it (probably a new position)
//										if (!$rs_update){
										if (mysql_affected_rows() == 0){

											// Now build the query to insert the data
											$query = "INSERT INTO ".$fund_positions_details_table." (fund_id, timestamp, first_trade_unix_date, last_trade_unix_date, ";

											// Tack on all the tag names
											for ($tag = 0; $tag < sizeof($$aTag); $tag++){
												$query .= ${$aTag}[$tag].", ";
											}

											// Pop the trailing ", " off
											$query = substr($query, 0, -2);

											// Now add the values
											$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".strtotime(${$aVal}[15]).", ".strtotime(${$aVal}[16]).", "; // 15 & 16 are firstTradeTimestamp and lastTradeTimestamp resp.

											// Tack on all the values
											for ($tag = 0; $tag < sizeof($$aVal); $tag++){
												if (${$aVal}[$tag] == ''){ // Blank decimal value, set it to NULL
													$query .= "NULL, ";
												}else{
													$query .= "'".${$aVal}[$tag]."', ";
												}
											}

											// Pop the trailing ", " off and close 'er up
											$query = substr($query, 0, -2);
											$query .= ")";
											echo $query."\r\n";

											// Do it!
											$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
											}
										// Initialize arrays
										$$aTag = array();
										$$aVal = array();

										$savePosition = false;

									}

									// If we hit a dividend or a changeaction (we must have if we are at level 5)
									if (${$aValues}[$element]['level'] == 5){

										// Close tag, just loop
										if (${$aValues}[$element]['type'] == "close"){
											continue;
										}else{
											// Process a dividend
											// NO LONGER DONE HERE BUT REMOVING THIS CODE BREAKS THE PROCESS SO GO THROUGH THE MOTIONS, FOR NOW.
											if (${$aValues}[$element]['tag'] == "ONEDIVIDEND" && ${$aValues}[$element]['type'] == "open"){

												// Initialize arrays
												$aDTag = array();
												$aDVal = array();

												// Stuff the tags and values
												for ($div = 0; $div < 4; $div++){
													$element++;
						  							array_push($aDTag, ${$aValues}[$element]['tag']);
													$value = addslashes(${$aValues}[$element]['value']);
													array_push($aDVal, $value);
												}

												// Now build the query to insert the data
												$query = "INSERT INTO ".$stock_dividends_table." (company_id, symbol, timestamp, ed_timestamp, rd_timestamp, pd_timestamp, ";

												// Tack on all the tag names
												for ($elementD = 0; $elementD < sizeof($aDTag); $elementD++){
													$query .= $aDTag[$elementD].", ";
												}

												// Pop the trailing ", " off
												$query = substr($query, 0, -2);

												// Now add the values
												$query .= ") VALUES ('".$companyID."', '".$stockSymbol."', UNIX_TIMESTAMP(), ".strtotime($aDVal[0]).", ".strtotime($aDVal[1]).", ".strtotime($aDVal[2]).", "; // 0, 1, & 2 are effectiveDate, recordDate, & payDate resp.

												// Tack on all the values
												for ($elementD = 0; $elementD < sizeof($aDVal); $elementD++){
													$query .= "'".$aDVal[$elementD]."', ";
												}

												// Pop the trailing ", " off and close 'er up
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";

												// Insert it
/////												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

											// Process a changeaction (CA)
											// NO LONGER DONE HERE BUT REMOVING THIS CODE BREAKS THE PROCESS SO GO THROUGH THE MOTIONS, FOR NOW.
											}else if (${$aValues}[$element]['tag'] == "ONEACTION" && ${$aValues}[$element]['type'] == "open"){

												// Initialize arrays
												$aCTag = array();
												$aCVal = array();

												// Stuff the tags and values
												for ($div = 0; $div < 2; $div++){
													$element++;
						  							array_push($aCTag, ${$aValues}[$element]['tag']);
													$value = addslashes(${$aValues}[$element]['value']);
													array_push($aCVal, $value);
												}

												// Now build the query to insert the data
												$query = "INSERT INTO ".$stock_changeactions_table." (company_id, symbol, timestamp, ed_timestamp, ";

												// Tack on all the tag names
												for ($elementC = 0; $elementC < sizeof($aCTag); $elementC++){
													$query .= $aCTag[$elementC].", ";
												}

												// Pop the trailing ", " off
												$query = substr($query, 0, -2);

												// Now add the values
												$query .= ") VALUES ('".$companyID."', '".$stockSymbol."', UNIX_TIMESTAMP(), ".strtotime($aCVal[0]).", "; // 0 is effectiveDate

												// Tack on all the values
												for ($elementC = 0; $elementC < sizeof($aCVal); $elementC++){
													$query .= "'".$aCVal[$elementC]."', ";
												}

												// Pop the trailing ", " off and close 'er up
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";

												// Insert it
/////												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

											}
										}
									}
								}
								break;


							case "tradesForPosition":
								$day = 0; // In case there is more than one day

								// Create VERY unique names for the two placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								// Parse the data
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
										$fundID = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['tag'] == "STARTDATE" && ${$aValues}[$element]['level'] == 2){ // Returned startDate
										$startDate = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['tag'] == "STOCKSYMBOL" && ${$aValues}[$element]['level'] == 2){ // Returned Stock Symbol
										$stockSymbol = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 2){ // About to start data

										// Initialize arrays
										${$aTag}[$day] = array();
										${$aVal}[$day] = array();
									}
									if (${$aValues}[$element]['level'] == 3){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
										// Push the tag name onto the tags array
										array_push(${$aTag}[$day], ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
//										if (strpos($value, "e")){ // Exponential number
//											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//										}
										// Push the value onto the values array
										array_push(${$aVal}[$day], $value);
									}
									if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 2){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\r\n\n";
										// Increment the day counter
										$day++;
									}
								}
//print_r($$aVal);

								// Get the stock ID so we can store it with each record
								$query = "	SELECT company_id
											FROM ".$stock_prices_table."
											WHERE symbol = '".$stockSymbol."'
											ORDER BY timestamp DESC
											LIMIT 1
								";
								//echo $query."\r\n";
								$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

/* Stop doing this and just write a zero if it's not found
								// If the stock is missing, go get it - rename these results and fire off a request for the missing stockInfo
								// By doing this we just keep passing on this one until the requested stockInfo is returned, then we can finally process it
								if (mysql_num_rows($rs_companyID) == 0){

									// Only do this if we haven't already
									if (!strpos($filename, "_stockInfo")){

										$query = "stockInfo|".$stockSymbol;
										//echo $query."\n";

										// Set the port number for the API call
										$port = rand(52000, 52499);

										// Execute the query call to submit the request
										exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

										// tack "_stockInfo" to the end of this output filename so we flag it
										rename($directory.$filename, $directory.$filename."_stockInfo");
									}
									// Bail entirely on this output file and move on to the next one
									continue 2;
								}
*/
								if (mysql_num_rows($rs_companyID) == 0){
									$companyID = 0;
								}else{
									$companyInfo = mysql_fetch_assoc($rs_companyID);
									$companyID = $companyInfo['company_id'];
								}
//echo $companyID."\n";

								// Delete all the existing records after the passed start date - we are replacing them with fresh ones
								$query = "	DELETE FROM ".$fund_trades_table."
											WHERE fund_id = '".$fundID."'
											AND stockSymbol = '".$stockSymbol."'
											AND unix_opened > ".mktime(0, 0, 0, substr($startDate, 4, 2), substr($startDate, 6, 2), substr($startDate, 0, 4))."
								";
								$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Now build the query to insert the data
								for ($row = 0; $row < sizeof($$aTag); $row++){
									$query = "INSERT INTO ".$fund_trades_table." (`fund_id`, `timestamp`, `unix_opened`, `unix_closed`, `company_id`, `stockSymbol`, ";
									// Tack on all the tag names
									for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
										$query .= "`".${$aTag}[$row][$element]."`, ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									// Now add the values
									$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[$row][0], 4, 2), substr(${$aVal}[$row][0], 6, 2), substr(${$aVal}[$row][0], 0, 4)).", ".mktime(0, 0, 0, substr(${$aVal}[$row][1], 4, 2), substr(${$aVal}[$row][1], 6, 2), substr(${$aVal}[$row][1], 0, 4)).", ".$companyID.", '".$stockSymbol."', ";
									// Tack on all the values
									for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
										$query .= "'".${$aVal}[$row][$element]."', ";
									}
									// Pop the trailing ", " off
									$query = substr($query, 0, -2);
									$query .= ")";
									//echo $query."\r\n";
									// Do it!
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								//echo "Success";
								break;


							case "tradesForFund":
								$day = 0; // In case there is more than one day

								// Create VERY unique names for the two placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								// Parse the data
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									switch (${$aValues}[$element]['level']){

										// <RESULTS>
										case 1:
											break;

										// Global Data
										case 2:
											if (${$aValues}[$element]['tag'] == "FUND_ID"){ // Returned FundID
												$fundID = trim(''.${$aValues}[$element]['value']);
											}
											if (${$aValues}[$element]['tag'] == "STARTDATE"){ // Returned startDate
												$startDate = trim(''.${$aValues}[$element]['value']);
											}
											break;

										// <POSITION>
										case 3:
//											if (${$aValues}[$element]['tag'] == "POSITION" && ${$aValues}[$element]['type'] == "open"){ // Hit a position
//												$day = 0; // In case there is more than one trade for this position
//											}
//											if (${$aValues}[$element]['tag'] == "POSITION" && ${$aValues}[$element]['type'] == "close"){ // Hit a position end
//												$day = 0;
//											}
											$day = 0;
											break;

										// Trade Data
										case 4:
											if (${$aValues}[$element]['tag'] == "STOCKSYMBOL"){ // Stock Symbol (duh)
												$stockSymbol = trim(''.${$aValues}[$element]['value']);
											}
											if (${$aValues}[$element]['tag'] == "TRADE" && ${$aValues}[$element]['type'] == "open"){ // About to start data

												// Initialize arrays
												${$aTag}[$day] = array();
												${$aVal}[$day] = array();
											}
											if (${$aValues}[$element]['tag'] == "TRADE" && ${$aValues}[$element]['type'] == "close"){ // Passed end of trade data
/*Stop doing this - we don't use it any longer
												// Get the stock ID so we can store it with each record
												$query = "	SELECT company_id
															FROM ".$stock_prices_table."
															WHERE symbol = '".$stockSymbol."'
															ORDER BY timestamp DESC
															LIMIT 1
												";
												//echo $query."\r\n";
												$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
												if (mysql_num_rows($rs_companyID) == 0){
													$companyID = 0;
												}else{
													$companyInfo = mysql_fetch_assoc($rs_companyID);
													$companyID = $companyInfo['company_id'];
												}
*/
												$companyID = 0;

												// Delete the existing record - we are replacing it with a fresh one
												// Since there are no unique columns, do our best to find the most exact match possible
												$query = "	DELETE FROM ".$fund_trades_table."
															WHERE ticketKey = '".$ticketKey."'
												";
												$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

												// Now build the query to insert the data
												$query = "INSERT INTO ".$fund_trades_table." (`fund_id`, `timestamp`, `unix_opened`, `unix_closed`, `company_id`, `stockSymbol`, ";
												// Tack on all the tag names
												for ($cnt = 0; $cnt < sizeof(${$aTag}[$day]); $cnt++){
													$query .= "`".${$aTag}[$day][$cnt]."`, ";
												}
												// Pop the trailing ", " off
												$query = substr($query, 0, -2);
												// Now add the values
												$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[$day][0], 4, 2), substr(${$aVal}[$day][0], 6, 2), substr(${$aVal}[$day][0], 0, 4)).", ".mktime(0, 0, 0, substr(${$aVal}[$day][1], 4, 2), substr(${$aVal}[$day][1], 6, 2), substr(${$aVal}[$day][1], 0, 4)).", ".$companyID.", '".$stockSymbol."', ";
												// Tack on all the values
												for ($cnt = 0; $cnt < sizeof(${$aVal}[$day]); $cnt++){
													$query .= "'".${$aVal}[$day][$cnt]."', ";
												}
												// Pop the trailing ", " off
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";
												// Do it!
												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

												// Increment the day counter
												$day++;
												// Initialize arrays
												${$aTag}[$day] = array();
												${$aVal}[$day] = array();
												$element++;
											}
											break;

										// Trade Details
										case 5:  // There be data here...
											// Push the tag name onto the tags array
											array_push(${$aTag}[$day], ${$aValues}[$element]['tag']);
											$value = addslashes(${$aValues}[$element]['value']);
//											if (strpos($value, "e")){ // Exponential number
//												$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//											}
	  										// Push the value onto the values array
											array_push(${$aVal}[$day], $value);
											// Store off the ticketKey for later use
											if (${$aValues}[$element]['tag'] == "TICKETKEY"){
												$ticketKey = $value;
											}
											break;

									}
								}
								//echo "Success";
								break;


							case "positionStratification":
								$position = 0; // In case there is more than one day

								// Get the stratification codes and stuff them into an array
								$query = "	SELECT *
											FROM ".$stock_stratification_codes_table."
								";
								$rs_codes = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Create arrays to hold stratification codes
								$aStyle	 = array();
								$aSector = array();

								// Stuff the arrays
								while ($codes = mysql_fetch_assoc($rs_codes)){
									if ($codes['style'] !== NULL){
										$aStyle[$codes['style']] = $codes['style_code'];
									}
									if ($codes['sector'] !== NULL){
										$aSector[$codes['sector']] = $codes['sector_code'];
									}
								}

								// Create VERY unique names for the two data placeholder arrays
								$aTag = "aTag".time().rand(0, 65535);
								$aVal = "aVal".time().rand(0, 65535);

								// Parse the data
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
										$fundID = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['tag'] == "STOCKSYMBOL" && ${$aValues}[$element]['level'] == 4){ // Returned Stock Symbol
										$stockSymbol = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 3){ // About to start data

										// Initialize arrays
										${$aTag}[$position] = array();
										${$aVal}[$position] = array();
									}
									if (${$aValues}[$element]['level'] == 4){ // There be data here...

										// Push the tag name onto the tags array
										array_push(${$aTag}[$position], ${$aValues}[$element]['tag']);
										$value = addslashes(${$aValues}[$element]['value']);
//										if (strpos($value, "e")){ // Exponential number
//											$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//										}
										// Push the value onto the values array
										array_push(${$aVal}[$position], $value);

										// Store off the Sector and Style values into their own arrays for later use
										if (${$aValues}[$element]['tag'] == "SECTOR"){ // Sector value
											$sector[$position] = trim(''.${$aValues}[$element]['value']);
										}
										if (${$aValues}[$element]['tag'] == "STYLE"){ // Style value
											$style[$position] = trim(''.${$aValues}[$element]['value']);
										}
									}
									if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 3){ // Passed end of data

										// Increment the day counter
										$position++;
									}
								}

								// Now write the positions

								// If there are no positions, write a dummy record that says so and bail
								if (sizeof($$aTag) < 1){
									$query =	"INSERT INTO ".$fund_stratification_basic_table." (
												fund_id,
												timestamp,
												stockSymbol,
												label
											) VALUES (
												'".$fundID."',
												UNIX_TIMESTAMP(),
												'99999',
												'No Positions'
											)";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									break;
								}

								// First invalidate the positions (zero shares)
								$query = "	UPDATE ".$fund_stratification_basic_table."
											SET	totalShares	= 0,
												currentValue= 0,
												fundRatio	= 0
											WHERE fund_id = '".$fundID."'
								";
								//echo $query."\r\n";
								$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Now build the query to update the active positions
								for ($row = 0; $row < sizeof($$aTag); $row++){

									// Get the stock symbol
									for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
										if (${$aTag}[$row][$element] == "STOCKSYMBOL"){
											$stockSymbol = ${$aVal}[$row][$element];
										}
									}

									// See if this position already exists
									$query = "	SELECT COUNT(*)
												FROM ".$fund_stratification_basic_table."
												WHERE fund_id = '".$fundID."'
												AND stockSymbol = '".$stockSymbol."'
									";
									//echo $query."\r\n";
									$rs_found = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									$found = mysql_fetch_array($rs_found);

									// If it does exist, update it
									if ($found[0] > 0){

										// Start with the fixed values
										$query = "	UPDATE ".$fund_stratification_basic_table."
													SET	fund_id	= '".$fundID."',
														timestamp = UNIX_TIMESTAMP(),
														sector_code = '".$aSector[$sector[$row]]."',
														style_code = '".$aStyle[$style[$row]]."',
										";

										// Now add the variable ones based on the XML tags and values
										for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
											$query .= ${$aTag}[$row][$element]." = '".${$aVal}[$row][$element]."', ";
//											if (${$aTag}[$row][$element] == "STOCKSYMBOL"){
//												$stockSymbol = ${$aVal}[$row][$element];
//											}
										}

										// Pop the trailing ", " off
										$query = substr($query, 0, -2);

										// Where clause
										$query .= " WHERE fund_id = '".$fundID."'";
										$query .= " AND stockSymbol = '".$stockSymbol."'";

										//echo $query."\r\n";
										// Do it!
										$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// It does not exist (probably a new position), insert it
									}else{

										$query = "INSERT INTO ".$fund_stratification_basic_table." (fund_id, timestamp, sector_code, style_code, ";
										// Tack on all the tag names
										for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
											$query .= ${$aTag}[$row][$element].", ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										// Now add the values
										$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), '".$aSector[$sector[$row]]."', '".$aStyle[$style[$row]]."',";
										// Tack on all the values
										for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
											$query .= "'".${$aVal}[$row][$element]."', ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										$query .= ")";
										//echo $query."\r\n";
										// Do it!
										$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									}

								}
								//echo "Success";
								break;


							case "stylePositionStratification":
								$stylesCounter = 0;
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['tag'] == "FUND_ID"){ // Returned FundID

										// Set the fund ID
										$fundID = trim(''.${$aValues}[$element]['value']);

										// Delete all the existing records - we are replacing them with fresh ones
										$query = "	DELETE FROM ".$fund_stratification_style_table."
													WHERE fund_id = '".$fundID."'
										";
										$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// Invalidate the positions (zero shares)
										$query = "	UPDATE ".$fund_stratification_style_positions_table."
													SET	totalShares	= 0,
														currentValue= 0,
														fundRatio	= 0
													WHERE fund_id = '".$fundID."'
										";
										//echo $query."\r\n";
										$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									}
									if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "open"){ // About to start a(nother) style slice

										// Initialize style arrays
										$aSTag = array();
										$aSVal = array();

										// Set the style ID
										$stylesCounter++;
										$styleID = $fundID."-".$stylesCounter;

									}
									if (${$aValues}[$element]['level'] == 4 && ${$aValues}[$element]['type'] == "complete"){ // There be style data here...

										// Push the tag name onto the tags array
										array_push($aSTag, ${$aValues}[$element]['tag']);

										// Push the value onto the values array
										$value = addslashes(${$aValues}[$element]['value']);
										array_push($aSVal, $value);

									}
									if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "open"){ // We're starting a(nother) position

										// Initialize position arrays
										$aPTag = array();
										$aPVal = array();

									}
									if (${$aValues}[$element]['level'] == 6 && ${$aValues}[$element]['type'] == "complete"){ // There be position data here...

										// Push the tag name onto the tags array
										array_push($aPTag, ${$aValues}[$element]['tag']);

										// Push the value onto the values array
										$value = addslashes(${$aValues}[$element]['value']);
										array_push($aPVal, $value);
									}
									if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position

										// See if this position already exists
										$query = "	SELECT COUNT(*)
													FROM ".$fund_stratification_style_positions_table."
													WHERE fund_id = '".$fundID."'
													AND stockSymbol = '".$stockSymbol."'
										";
										//echo $query."\r\n";
										$rs_found = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
										$found = mysql_fetch_array($rs_found);

										// If it does exist, update it
										if ($found[0] > 0){

											// Build query to update the position row
											// Start with the fixed values
											$query = "	UPDATE ".$fund_stratification_style_positions_table."
														SET	fund_id	= '".$fundID."',
															style_id	= '".$styleID."',
															timestamp = UNIX_TIMESTAMP(),
											";

											// Now add the variable ones based on the XML tags and values
											for ($column = 0; $column < sizeof($aPTag); $column++){
												$query .= $aPTag[$column]." = '".$aPVal[$column]."', ";
												if ($aPTag[$column] == "STOCKSYMBOL"){
													$stockSymbol = $aPVal[$column];
												}
											}

											// Pop the trailing ", " off
											$query = substr($query, 0, -2);

											// Where clause
											$query .= " WHERE fund_id = '".$fundID."'";
											$query .= " AND stockSymbol = '".$stockSymbol."'";

											//echo $query."\r\n";
											// Do it!

											$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// It does not exist (probably a new position), insert it
										}else{

											// Build the query to insert the positions data for this style
											$query = "INSERT INTO ".$fund_stratification_style_positions_table." (fund_id, style_id, timestamp, ";
											// Tack on all the tag names
											for ($column = 0; $column < sizeof($aPTag); $column++){
												$query .= $aPTag[$column].", ";
											}
											// Pop the trailing ", " off
											$query = substr($query, 0, -2);
											// Now add the values
											$query .= ") VALUES ('".$fundID."', '".$styleID."', UNIX_TIMESTAMP(), ";
											// Tack on all the values
											for ($value = 0; $value < sizeof($aPVal); $value++){
												$query .= "'".$aPVal[$value]."', ";
											}
											// Pop the trailing ", " off
											$query = substr($query, 0, -2);
											$query .= ")";
											//echo $query."\r\n";
											// Do it!
											$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
										}
									}
									if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position

										// Build the query to insert the style data
										$query = "INSERT INTO ".$fund_stratification_style_table." (fund_id, style_id, timestamp, ";
										// Tack on all the tag names
										for ($column = 0; $column < sizeof($aSTag); $column++){
											$query .= $aSTag[$column].", ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										// Now add the values
										$query .= ") VALUES ('".$fundID."', '".$styleID."', UNIX_TIMESTAMP(), ";
										// Tack on all the values
										for ($value = 0; $value < sizeof($aSVal); $value++){
											$query .= "'".$aSVal[$value]."', ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										$query .= ")";
										//echo $query."\r\n";
										// Do it!
										$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									}
								}
								// If there are no styles, write a dummy record that says so and bail
								if ($stylesCounter < 1){
									$query =	"INSERT INTO ".$fund_stratification_style_table." (
												fund_id,
												style_id,
												timestamp,
												styleName
											) VALUES (
												'".$fundID."',
												'".$fundID."-99999',
												UNIX_TIMESTAMP(),
												'No Positions'
											)";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								break;


							case "sectorPositionStratification":
								$sectorsCounter = 0;
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['tag'] == "FUND_ID"){ // Returned FundID

										// Set the fund ID
										$fundID = trim(''.${$aValues}[$element]['value']);

										// Delete all the existing records - we are replacing them with fresh ones
										$query = "	DELETE FROM ".$fund_stratification_sector_table."
													WHERE fund_id = '".$fundID."'
										";
										$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// Invalidate the positions (zero shares)
										$query = "	UPDATE ".$fund_stratification_sector_positions_table."
													SET	totalShares	= 0,
														currentValue= 0,
														fundRatio	= 0
													WHERE fund_id = '".$fundID."'
										";
										//echo $query."\r\n";
										$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									}
									if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "open"){ // About to start n(nother) style slice

										// Initialize style arrays
										$aSTag = array();
										$aSVal = array();

										// Set the style ID
										$sectorsCounter++;
										$sectorID = $fundID."-".$sectorsCounter;

									}
									if (${$aValues}[$element]['level'] == 4 && ${$aValues}[$element]['type'] == "complete"){ // There be sector data here...

										// Push the tag name onto the tags array
										array_push($aSTag, ${$aValues}[$element]['tag']);

										// Push the value onto the values array
										$value = addslashes(${$aValues}[$element]['value']);
										array_push($aSVal, $value);

									}
									if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "open"){ // We're starting a(nother) position

										// Initialize position arrays
										$aPTag = array();
										$aPVal = array();

									}
									if (${$aValues}[$element]['level'] == 6 && ${$aValues}[$element]['type'] == "complete"){ // There be position data here...

										// Push the tag name onto the tags array
										array_push($aPTag, ${$aValues}[$element]['tag']);

										// Push the value onto the values array
										$value = addslashes(${$aValues}[$element]['value']);
										array_push($aPVal, $value);

										if (${$aValues}[$element]['tag'] == "STOCKSYMBOL"){
											$stockSymbol = $value;
										}

									}
									if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position

										// See if this position already exists
										$query = "	SELECT COUNT(*)
													FROM ".$fund_stratification_sector_positions_table."
													WHERE fund_id = '".$fundID."'
													AND stockSymbol = '".$stockSymbol."'
										";
										//echo $query."\r\n";
										$rs_found = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
										$found = mysql_fetch_array($rs_found);

										// If it does exist, update it
										if ($found[0] > 0){

											// Build query to update the position row
											// Start with the fixed values
											$query = "	UPDATE ".$fund_stratification_sector_positions_table."
														SET	fund_id	= '".$fundID."',
															sector_id	= '".$sectorID."',
															timestamp = UNIX_TIMESTAMP(),
											";

											// Now add the variable ones based on the XML tags and values
											for ($column = 0; $column < sizeof($aPTag); $column++){
												$query .= $aPTag[$column]." = '".$aPVal[$column]."', ";
//												if ($aPTag[$column] == "STOCKSYMBOL"){
//													$stockSymbol = $aPVal[$column];
//												}
											}

											// Pop the trailing ", " off
											$query = substr($query, 0, -2);

											// Where clause
											$query .= " WHERE fund_id = '".$fundID."'";
											$query .= " AND stockSymbol = '".$stockSymbol."'";

											//echo $query."\r\n";
											// Do it!

											$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

										// It does not exist (probably a new position), insert it
										}else{

											// Build the query to insert the positions data for this sector
											$query = "INSERT INTO ".$fund_stratification_sector_positions_table." (fund_id, sector_id, timestamp, ";
											// Tack on all the tag names
											for ($column = 0; $column < sizeof($aPTag); $column++){
												$query .= $aPTag[$column].", ";
											}
											// Pop the trailing ", " off
											$query = substr($query, 0, -2);
											// Now add the values
											$query .= ") VALUES ('".$fundID."', '".$sectorID."', UNIX_TIMESTAMP(), ";
											// Tack on all the values
											for ($value = 0; $value < sizeof($aPVal); $value++){
												$query .= "'".$aPVal[$value]."', ";
											}
											// Pop the trailing ", " off
											$query = substr($query, 0, -2);
											$query .= ")";
											//echo $query."\r\n";
											// Do it!
											$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
										}
									}
									if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position

										// Build the query to insert the sector data
										$query = "INSERT INTO ".$fund_stratification_sector_table." (fund_id, sector_id, timestamp, ";
										// Tack on all the tag names
										for ($column = 0; $column < sizeof($aSTag); $column++){
											$query .= $aSTag[$column].", ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										// Now add the values
										$query .= ") VALUES ('".$fundID."', '".$sectorID."', UNIX_TIMESTAMP(), ";
										// Tack on all the values
										for ($value = 0; $value < sizeof($aSVal); $value++){
											$query .= "'".$aSVal[$value]."', ";
										}
										// Pop the trailing ", " off
										$query = substr($query, 0, -2);
										$query .= ")";
										//echo $query."\r\n";
										// Do it!
										$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									}
								}
								// If there are no sectors, write a dummy record that says so and bail
								if ($sectorsCounter < 1){
									$query =	"INSERT INTO ".$fund_stratification_sector_table." (
												fund_id,
												sector_id,
												timestamp,
												sectorName
											) VALUES (
												'".$fundID."',
												'".$fundID."-99999',
												UNIX_TIMESTAMP(),
												'No Positions'
											)";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								break;


							// Stock Info
							case "stockInfo":
								for ($element = 0; $element <= sizeof($$aValues); $element++){
									// This currently comes in at the END of the XML results so it wouldn't find it until after traversing the entire result set.
									// Therefore it's not very useful but since the FundID is not actually used for anything yet it's OK.
									// I just put this code here for future use....
									if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
										$fundID = trim(''.${$aValues}[$element]['value']);
									}
									if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

										// Create VERY unique name for the placeholder array
										$aStockInfo = "aStockInfo".time().rand(0, 65535);

										// Initialize arrays
										$$aStockInfo = array();
									}
									if (${$aValues}[$element]['level'] == 2){ // There be data here...
										${$aStockInfo}[${$aValues}[$element]['tag']] = addslashes(${$aValues}[$element]['value']);
									}
									// If there is no marketcap the API returns "None".  Since this value must be numeric, make it 0
									if (${$aValues}[$element]['tag'] == "MARKETCAP" && ${$aStockInfo}[${$aValues}[$element]['tag']] == "None"){
										${$aStockInfo}[${$aValues}[$element]['tag']] = 0;
									}
								}
//print_r($aStockInfo);

								// See if the company is already in the stock_companies table (probably is)
								$query =	"SELECT *
											 FROM ".$stock_companies_table."
											 WHERE company_name = '".${$aStockInfo}['NAME']."'
											";
								$rs_select = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								// WOW!  It isn't (SURPRISE!)  Let's add it.
								if (mysql_num_rows($rs_select) == 0){
									$query =	"INSERT INTO ".$stock_companies_table." (
													company_id,
													company_name,
													timestamp
												 ) SELECT COALESCE((SELECT MAX(company_id) FROM ".$stock_companies_table."), 0) + 1,
												 '".${$aStockInfo}['NAME']."',
												 UNIX_TIMESTAMP()
												";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
									// Read it back to get the new company_id
									$query =	"SELECT *
												 FROM ".$stock_companies_table."
												 WHERE company_name = '".${$aStockInfo}['NAME']."'
												";
									$rs_select = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
								$company = mysql_fetch_assoc($rs_select);
								$companyID = $company['company_id'];
/* Decided to replace the data every time, just in case something changes
								// See if the stock symbol is already in the stock_symbols table (probably is)
								$query =	"SELECT *
											 FROM ".$stock_symbols_table."
											 WHERE symbol = '".${$aStockInfo}['SYMBOL']."'
											";
								$rs_select = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								// WOW!  It isn't (SURPRISE!)  Let's add it.
								if (mysql_num_rows($rs_select) == 0){
									$query =	"INSERT INTO ".$stock_symbols_table." (
													company_id,
													company,
													symbol,
													exchange,
													sector,
													style,
													timestamp
												) VALUES (
													".$companyID.",
													'".${$aStockInfo}['NAME']."',
													'".${$aStockInfo}['SYMBOL']."',
													'".${$aStockInfo}['EXCHANGE']."',
													'".${$aStockInfo}['SECTOR']."',
													'".${$aStockInfo}['STYLE']."',
													UNIX_TIMESTAMP()
												)";
									//echo $query."\r\n";
									$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								}
*/
								// Delete the row if it is already in the stock_symbols table (probably is)
								$query =	"DELETE
											 FROM ".$stock_symbols_table."
											 WHERE symbol = '".${$aStockInfo}['SYMBOL']."'
											";
								$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								// Now insert a fresh record.
								$query =	"INSERT INTO ".$stock_symbols_table." (
												company_id,
												company,
												symbol,
												exchange,
												sector,
												style,
												timestamp
											) VALUES (
												".$companyID.",
												'".${$aStockInfo}['NAME']."',
												'".${$aStockInfo}['SYMBOL']."',
												'".${$aStockInfo}['EXCHANGE']."',
												'".${$aStockInfo}['SECTOR']."',
												'".${$aStockInfo}['STYLE']."',
												UNIX_TIMESTAMP()
											)";
								//echo $query."\r\n";
								$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Delete the existing current price record - we are replacing it with a fresh one
								$query = "	DELETE FROM ".$stock_prices_table."
											WHERE symbol = '".${$aStockInfo}['SYMBOL']."'
								";
								$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

								// Insert the current price info
								$query =	"INSERT INTO ".$stock_prices_table." (
												company_id,
												symbol,
												timestamp,
												currentPrice,
												lastPrice,
												netChange,
												todayReturn,
												moving50DayAvgClosed,
												moving200DayAvgClosed,
												oneYearHigh,
												dayHigh,
												oneYearLow,
												dayLow,
												netVolume,
												moving50DayAvgVolume,
												shortVolume,
												marketcap
											) VALUES (
												".$companyID.",
												'".${$aStockInfo}['SYMBOL']."',
												UNIX_TIMESTAMP(),
												".${$aStockInfo}['CURRENTPRICE'].",
												".${$aStockInfo}['LASTPRICE'].",
												".${$aStockInfo}['NETCHANGE'].",
												".${$aStockInfo}['TODAYRETURN'].",
												".${$aStockInfo}['MOVING50DAYAVGCLOSED'].",
												".${$aStockInfo}['MOVING200DAYAVGCLOSED'].",
												".${$aStockInfo}['ONEYEARHIGH'].",
												".${$aStockInfo}['DAYHIGH'].",
												".${$aStockInfo}['ONEYEARLOW'].",
												".${$aStockInfo}['DAYLOW'].",
												".${$aStockInfo}['NETVOLUME'].",
												".${$aStockInfo}['MOVING50DAYAVGVOLUME'].",
												".${$aStockInfo}['SHORTVOLUME'].",
												".${$aStockInfo}['MARKETCAP']."
											)";
								//echo $query."\r\n";
								$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
								//echo "Success";
								break;


							case "stockActions":
								for ($element = 0; $element <= sizeof($$aValues); $element++){

									// Skip any with no values
									if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['type'] == "complete" && !isset(${$aValues}[$element]['value'])){
										continue;
									}
									if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['type'] == "complete" && isset(${$aValues}[$element]['value'])){
										$tag = ${$aValues}[$element]['tag'];
										$$tag = ${$aValues}[$element]['value'];
									}
									if (isset($SYMBOL)){

										// Reassign for better readability
										$stockSymbol = $SYMBOL;

										// Unset $SYMBOL - used as flag to trigger delete
										unset($SYMBOL);

										// Get the company_id for this symbol for CA records
										$query = "	SELECT company_id
													FROM ".$stock_prices_table."
													WHERE symbol = '".$stockSymbol."'
													ORDER BY timestamp DESC
													LIMIT 1
										";
										$rs_companyID = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!"
										$companyInfo = mysql_fetch_assoc($rs_companyID);
										$companyID = $companyInfo['company_id'];
										// Delete all the existing CA records - we are replacing them with fresh ones
										$query = "	DELETE FROM ".$stock_changeactions_table."
													WHERE company_id = '".$companyID."'
													AND symbol = '".$stockSymbol."'
										";
										$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									}
									if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['type'] == "open"){ // About to start a CA type

										// Get the CA type
										$action = substr(${$aValues}[$element]['tag'], 0, -1);
										if ($action == "BANKRUPTCIE"){
											$action = "BANKRUPTCY";
										}

										while (!(${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['type'] == "close")){
											if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['tag'] == "ONEACTION"){

												// Initialize arrays
												$aTag = array();
												$aVal = array();

												$element++;
												while (!(${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['tag'] == "ONEACTION")){

													// Push the tag name onto the tags array
													array_push($aTag, ${$aValues}[$element]['tag']);

													// Push the value onto the values array
													array_push($aVal, addslashes(${$aValues}[$element]['value']));

													// Push timestamps onto the stack
													if (stripos(${$aValues}[$element]['tag'], "date") !== false){ // The word "date" is in the tag name
														array_push($aTag, ${$aValues}[$element]['tag']."_timestamp");
														$value = mktime(0, 0, 0, substr(${$aValues}[$element]['value'], 4, 2), substr(${$aValues}[$element]['value'], 6, 2), substr(${$aValues}[$element]['value'], 0, 4));
														array_push($aVal, $value);
													}

						  							$element++;
												}

												// Now build the query to insert the data
												$query = "INSERT INTO ".$stock_changeactions_table." (company_id, symbol, action, timestamp, ";

												// Tack on all the tag names
												for ($tag = 0; $tag < sizeof($aTag); $tag++){
													$query .= $aTag[$tag].", ";
												}

												// Pop the trailing ", " off
												$query = substr($query, 0, -2);

												// Now add the values
												$query .= ") VALUES ('".$companyID."', '".$stockSymbol."', '".$action."', UNIX_TIMESTAMP(), ";

												// Tack on all the values
												for ($val = 0; $val < sizeof($aVal); $val++){
													$query .= "'".$aVal[$val]."', ";
												}

												// Pop the trailing ", " off and close 'er up
												$query = substr($query, 0, -2);
												$query .= ")";
												//echo $query."\r\n";

												// Insert it
												$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

											}
											$element++;
										}
									}
								}
								//echo "Success";
								break;

						}
					}
					// archive or delete the file
					if ($aProcess[$folder] == "ecn"){
						rename($directory.$filename, $directory."history/".$filename);
					}else{
						unlink($directory.$filename);
					}
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