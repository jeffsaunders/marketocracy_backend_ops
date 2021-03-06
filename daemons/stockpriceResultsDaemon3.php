<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any stockprice API call submitted through the legacyData daemon
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
$aProcess = array('stockprice');

// Define which API server instance we are running (folder, name)
$aAPI = array('api3','API3');

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
//ini_set("auto_detect_line_endings", true);
					$fh = fopen($directory.$filename, "r");

					// Rip it's guts out
					$contents = fread($fh, filesize($directory.$filename)+1);
//echo $contents."\n";
					// Sew it back up
					fclose($fh);

// code that's supposed to handle "Word" characters...need more testing
//$namedEntities = array_flip(
//  array_diff(
//    get_html_translation_table(HTML_ENTITIES, ENT_NOQUOTES),
//    get_html_translation_table(HTML_SPECIALCHARS, ENT_NOQUOTES)
//  )
//);
//$contents = strtr($contents, $namedEntities);

					// Create a VERY random unique name for the results array
					$aValues = "aValues".time().rand(0, 65535);

					// Parse the contents XML for DB insertion
					$parser = xml_parser_create();
//xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
//xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
//xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
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
//echo $fundID."\n";
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
//print_r($$aTag);print_r($$aVal);//die();
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
//die();
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
//					if ($aProcess[$folder] == "ecn"){
//						rename($directory.$filename, $directory."history/".$filename);
//					}else{
						unlink($directory.$filename);
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