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
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define process and use to define directory for results
$process = "trade";

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
//print_r($xml);
//die();
				// Grab the method
				$method	= $xml->method;

				// Process the results depending on the method specified
				switch ($method){

					//--------------------------------------------------
					case "positionDetail":

						// This method actually writes to the members_fund_positions table (not _details), despite the naming confusion

						// Set main level vars
						$fundID			= $xml->fund_ID;
						$date			= $xml->date;
						$aPositionsList	= $xml->positionsList;

						// If there are no positions, write a dummy record that says so and bail
						if (sizeof($aPositionsList) < 1){
							$query = "
								INSERT INTO ".$fund_positions_table." (
									fund_id,
									timestamp,
									date,
									unix_date,
									company_id,
									stockSymbol,
									name
								)VALUES(
									:fund_id,
									UNIX_TIMESTAMP(),
									:date,
									:unix_date,
									0,
									:stockSymbol,
									:name
								)
							";
							try{
								$rsInsert = $mLink->prepare($query);
								$aValues = array(
									':fund_id'	  	=> $fundID,
									':date'		  	=> $date,
									':unix_date'  	=> mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4)),
									':stockSymbol'	=> '99999',
									':name'		  	=> 'No Positions'
								);
								$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
								echo $preparedQuery."\n";
								$rsInsert->execute($aValues);
							}
							catch(PDOException $error){
								// Log any error
								file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
							}
							break;
						}

						// Delete all the existing records for the passed start date - we are replacing them with fresh ones
						$query = "	DELETE FROM ".$fund_positions_table."
									WHERE fund_id = :fund_id
									AND date = :date
							";
						try{
							$rsDelete = $mLink->prepare($query);
							$aValues = array(
								':fund_id'	=> $fundID,
								':date'		=> $date
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsDelete->execute($aValues);
						}

						catch(PDOException $error){
							// Log any error
							file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						// loop through the positions
						foreach($aPositionsList->position as $key=>$position){

							// Set trade level vars
							$unix_date		= mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4));
							$stockSymbol	= $position->stockSymbol;
							$name			= $position->name;
							$shares			= $position->shares;
							$dividends		= $position->dividends;
							$value			= $position->value;
							$ratio			= $position->ratio;
							$price			= $position->price;

							// Insert row
							$query = "
								INSERT INTO ".$fund_positions_table." (
									fund_id,
									timestamp,
									date,
									unix_date,
									company_id,
									stockSymbol,
									name,
									shares,
									dividends,
									value,
									ratio,
									price
								)VALUES(
									:fund_id,
									UNIX_TIMESTAMP(),
									:date,
									:unix_date,
									0,
									:stockSymbol,
									:name,
									:shares,
									:dividends,
									:value,
									:ratio,
									:price
								)
							";
							try{
								$rsInsert = $mLink->prepare($query);
								$aValues = array(
									':fund_id'	  	=> $fundID,
									':date'		  	=> $date,
									':unix_date'  	=> $unix_date,
									':stockSymbol'	=> $stockSymbol,
									':name'		  	=> $name,
									':shares'	  	=> $shares,
									':dividends'  	=> $dividends,
									':value'	  	=> $value,
									':ratio'	  	=> $ratio,
									':price'	  	=> $price
								);
								$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
								//echo $preparedQuery."\n";
								$rsInsert->execute($aValues);
							}
							catch(PDOException $error){
								// Log any error
								file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
							}

						} // End loop for $aPositionsList
						break;


					//--------------------------------------------------
					case "tradesForPosition":

						// Set main level vars
						$fundID			= $xml->fund_ID;
						$startDate		= $xml->startDate;
						$stockSymbol	= $xml->stockSymbol;
						$aTrades		= $xml->trade;

						// Delete all the existing records after the passed start date - we are replacing them with fresh ones
						$query = "	DELETE FROM ".$fund_trades_table."
									WHERE fund_id = :fund_id
									AND stockSymbol = :stockSymbol
									AND unix_opened > :startDate
						";
						try{
							$rsDelete = $mLink->prepare($query);
							$aValues = array(
								':fund_id'		=> $fundID,
								':stockSymbol'	=> $stockSymbol,
								':startDate'	=> mktime(0, 0, 0, substr($startDate, 4, 2), substr($startDate, 6, 2), substr($startDate, 0, 4))
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsDelete->execute($aValues);
						}

						catch(PDOException $error){
							// Log any error
							file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						// loop through each trade within the position level
						foreach($aTrades as $key=>$trade){

							// Set trade level vars
							$opened			= $trade->opened;
							$unix_opened	= mktime(5,0,0,substr($opened,4,2),substr($opened,6,2),substr($opened,0,4));
							$closed			= $trade->closed;
							$unix_closed	= mktime(5,0,0,substr($closed,4,2),substr($closed,6,2),substr($closed,0,4));
							$sharesOrdered	= $trade->sharesOrdered;
							$sharesFilled	= $trade->sharesFilled;
							$price			= $trade->price;
							$limit			= $trade->limit;
							$createdByCA	= $trade->createdByCA;
							$net			= $trade->net;
							$secFee			= $trade->secFee;
							$commission		= $trade->commission;
							$buyOrSell		= $trade->buyOrSell;
							$dayOrGTC		= $trade->dayOrGTC;
							$ticketKey		= $trade->ticketKey;
							$comment		= rawurldecode($trade->comment);

							// Insert row
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
									buyOrSell,
									comment
								)VALUES(
									:fund_id,
									UNIX_TIMESTAMP(),
									0,
									:stockSymbol,
									:opened,
									:unix_opened,
									:closed,
									:unix_closed,
									:sharesOrdered,
									:sharesFilled,
									:price,
									:limit,
									:dayOrGTC,
									:ticketKey,
									'closed',
									:createdByCA,
									:net,
									:secFee,
									:commission,
									:buyOrSell,
									:comment
								)
							";
							try{
								$rsInsert = $mLink->prepare($query);
								$aValues = array(
									':fund_id'			=> $fundID,
									':stockSymbol'		=> $stockSymbol,
									':opened'			=> $opened,
									':unix_opened'		=> $unix_opened,
									':closed'			=> $closed,
									':unix_closed'		=> $unix_closed,
									':sharesOrdered'	=> $sharesOrdered,
									':sharesFilled'		=> $sharesFilled,
									':price'			=> $price,
									':limit'			=> $limit,
									':dayOrGTC'			=> $dayOrGTC,
									':ticketKey'		=> $ticketKey,
									':createdByCA'		=> $createdByCA,
									':net'				=> $net,
									':secFee'			=> $secFee,
									':commission'		=> $commission,
									':buyOrSell'		=> $buyOrSell,
									':comment'			=> $comment

								);
								$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
								//echo $preparedQuery."\n";
								$rsInsert->execute($aValues);
							}
							catch(PDOException $error){
								// Log any error
								file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
							}

						} // End loop for $aTrades
						break;


					//--------------------------------------------------
					case "tradesForFund":

						// Set main level vars
						$fundID			= $xml->fund_ID;
						$startDate		= $xml->startDate;
						$aPositionList	= $xml->positionsList;
						$startTimestamp = mktime(5,0,0,substr($startDate,4,2),substr($startDate,6,2),substr($startDate,0,4));

						// Delete existing values for fundID
						$query = "
							DELETE FROM ".$fund_trades_table."
							WHERE fund_id = :fund_id
							AND unix_closed >= :start_timestamp
						";
						try{
							$rsDelete = $mLink->prepare($query);
							$aValues = array(
								':fund_id'			=> $fundID,
								':start_timestamp'	=> $startTimestamp
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsDelete->execute($aValues);
						}

						catch(PDOException $error){
							// Log any error
							file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						// loop through the positions
						foreach($aPositionList->position as $key=>$aPositions){

							// Set position level vars
							$stockSymbol	= $aPositions->stockSymbol;
							$aTrades 		= $aPositions->trade;

							// loop through each trade within the position level
							foreach($aTrades as $key=>$trade){

								// Set trade level vars
								$opened			= $trade->opened;
								$unix_opened	= mktime(5,0,0,substr($opened,4,2),substr($opened,6,2),substr($opened,0,4));
								$closed			= $trade->closed;
								$unix_closed	= mktime(5,0,0,substr($closed,4,2),substr($closed,6,2),substr($closed,0,4));
								$sharesOrdered	= $trade->sharesOrdered;
								$sharesFilled	= $trade->sharesFilled;
								$price			= $trade->price;
								$limit			= $trade->limit;
								$createdByCA	= $trade->createdByCA;
								$net			= $trade->net;
								$secFee			= $trade->secFee;
								$commission		= $trade->commission;
								$buyOrSell		= $trade->buyOrSell;
								$dayOrGTC		= $trade->dayOrGTC;
								$ticketKey		= $trade->ticketKey;
								$comment		= rawurldecode($trade->comment);

								// Insert row
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
										buyOrSell,
										comment
									)VALUES(
										:fund_id,
										UNIX_TIMESTAMP(),
										0,
										:stockSymbol,
										:opened,
										:unix_opened,
										:closed,
										:unix_closed,
										:sharesOrdered,
										:sharesFilled,
										:price,
										:limit,
										:dayOrGTC,
										:ticketKey,
										'closed',
										:createdByCA,
										:net,
										:secFee,
										:commission,
										:buyOrSell,
										:comment
									)
								";
								try{
									$rsInsert = $mLink->prepare($query);
									$aValues = array(
										':fund_id'			=> $fundID,
										':stockSymbol'		=> $stockSymbol,
										':opened'			=> $opened,
										':unix_opened'		=> $unix_opened,
										':closed'			=> $closed,
										':unix_closed'		=> $unix_closed,
										':sharesOrdered'	=> $sharesOrdered,
										':sharesFilled'		=> $sharesFilled,
										':price'			=> $price,
										':limit'			=> $limit,
										':dayOrGTC'			=> $dayOrGTC,
										':ticketKey'		=> $ticketKey,
										':createdByCA'		=> $createdByCA,
										':net'				=> $net,
										':secFee'			=> $secFee,
										':commission'		=> $commission,
										':buyOrSell'		=> $buyOrSell,
										':comment'			=> $comment
									);
									$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
									//echo $preparedQuery."\n";
									$rsInsert->execute($aValues);
								}
								catch(PDOException $error){
									// Log any error
									file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
								}

							} // End loop for $aTrades
						} // End loop for $aPositionList
						break;


					//--------------------------------------------------
					case "untrade":

						// Set main level vars
						$ticketKey = $xml->ticketKey;

						// Get fund_id and trade date from the trades history table
						$query = "
							SELECT t.fund_id, t.closed, f.fund_symbol, m.username
							FROM members_fund_trades t, members_fund f, members m
							WHERE UPPER(t.ticketKey) = :ticket_key
							AND f.fund_id = t.fund_id
							AND m.member_id = f.member_id
						";
						try{
							$rsTicketInfo = $mLink->prepare($query);
							$aValues = array(
								':ticket_key'	=> strtoupper($ticketKey)
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsTicketInfo->execute($aValues);
						}

						catch(PDOException $error){
							// Log any error
							file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						// Assign the values
						$ticket = $rsTicketInfo->fetch(PDO::FETCH_ASSOC);

						// Build API query to pull new trade history
						$query = "tradesForFund|".$ticket['username']."|".$ticket['fund_id']."|".$ticket['fund_symbol']."|".$ticket['closed'];
						//echo $query."\n"; die();

						// Set the port number for the API call
						$port = rand(52000, 52099);

						// Execute the query call to submit the request
						exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

						break;

				}  // End switch

				// Delete file
				unlink($directory.$filename);

			} // End process file with "_output"

		} // End reading file

		// Close up the directory
		closedir($dh);

	} // End open directory

	// No more files, bail.
	break;
} // End
?>