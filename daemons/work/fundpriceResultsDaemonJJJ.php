<?php
/*
This process runs as a continual server daemon.
It's purpose is to process the results of any fundprice API call submitted through the legacyData daemon
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
$aProcess = array('fundprice');

// Define which API server instance we are running (folder, name)
$aAPI = array('api2','API2');

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
					unset($filename);
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
								// Now build the queries to delete the old (if it exists) and insert the new data
								for ($row = 0; $row < sizeof($$aTag); $row++){

									// Delete the old (if it exists)
									$query = "DELETE FROM ".$fund_pricing_table." WHERE fund_id = '".$fundID."' AND date = '".${$aVal}[$row][0]."'";
//echo $query."\r\n";
									$rs_delete = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

									// Insert the new
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

						}
					}
					// archive or delete the file
//					if ($aProcess[$folder] == "ecn"){
//						rename($directory.$filename, $directory."history/".$filename);
//					}else{
						unlink($directory.$filename);
//						unset($filename);
//					}
				}
				unset($filename);
			}
			// Close up the directory
			closedir($dh);
//die();
		}
	}
break;
}
?>