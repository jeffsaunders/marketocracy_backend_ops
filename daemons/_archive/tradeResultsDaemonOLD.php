<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any trade API calls submitted through the legacyData daemons
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
$aProcess = array('trade');

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

						}
					}
					// archive or delete the file
//					if ($aProcess[$folder] == "ecn"){
//						rename($directory.$filename, $directory."history/".$filename);
//					}else{
						unlink($directory.$filename);
// 					}
					unset($aValues);
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