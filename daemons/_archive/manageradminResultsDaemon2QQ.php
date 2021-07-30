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
//$aProcess = array('fundprice','stockprice','ecn','manageradmin','trade');
//$aProcess = array('fundprice','stockprice','ecn','manageradmin');
$aProcess = array('manageradmin');

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
					$contents = fread($fh, filesize($directory.$filename)+1);
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


							case "deactivateFund":
								// Nothing to do...
// check <status> value for logging.
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