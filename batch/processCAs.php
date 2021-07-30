<?php
/*
This process applies imported Corporate Actions where applicable.
By default it uses the same start and end dates, to get "yesterday's" CAs.
If you want to specify start and end dates manually, edit them below.
*/

// Load debug functions
date_default_timezone_set('America/New_York');
error_reporting(E_ALL);  // Show ALL, including warnings and notices
//error_reporting(E_ERROR);  // Just show hard errors
ini_set('display_errors', '1');  // Show 'em

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Declare all the CA methods
/*
$aMethods = array(
			"cashDividendsOnDate",
			"stockDividendsOnDate",
			"splitsOnDate",
			"spinoffsOnDate",
			"symbolChangesOnDate",
			"cusipChangesOnDate",
			"acquisitionsOnDate",
			"bankruptciesOnDate",
			"listingsOnDate",
			"delistingsOnDate",
			"nameChangesOnDate",
			"listingChangesOnDate"
			);
*/
// Just define the methods we are about right now....
$aMethods = array(
			"symbolChangesOnDate",
			);

// Set the start and end dates
// If it's Tuesday morning, run for Sat, Sun, and Mon.
if (date("w") == 2){
	$startDate = date("Ymd", strtotime("-3 days"));
	$endDate = date("Ymd", strtotime("-1 day"));
}else{
	$startDate = date("Ymd", strtotime("-1 day"));
	$endDate = $startDate;
}

// Manual override (uncomment)
//$startDate = "20160701";
//$endDate = "20160725";

for ($x = 0; $x < sizeof($aMethods); $x++){

	// What CA method are we processing?
	switch ($aMethods[$x]){

		// Symbol Changes
		case "symbolChangesOnDate":

			// Get all the symbol change CAs for the applicable date(s)
			$query = "
				SELECT symbol as oldSymbol, newSymbol
				FROM ".$stock_corporate_actions_table."
				WHERE method = :method
				AND date >= :startDate
				AND date <=  :endDate
			";
			try{
				$rsCAs = $mLink->prepare($query);
				$aValues = array(
					':method'	 	=> $aMethods[$x],
					':startDate' 	=> $startDate,
					':endDate'	 	=> $endDate
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery;die();
				$rsCAs->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
			while($CAs = $rsCAs->fetch(PDO::FETCH_ASSOC)){

				// Assign variable for easier documenting
				$oldSymbol	= $CAs['oldSymbol'];
				$newSymbol	= $CAs['newSymbol'];

				// Set the API port(s)
				// API2
				$portFloor = 52100;
				$portCeil = 52499;
				// API3 (Testing)
				//$portFloor = 52500;
				//$portCeil = 52599;

				// Initialize starting port
				$port = rand($portFloor, $portCeil);

				// Get the funds that hold this stock
				$query = "
					SELECT strat.fund_id, fund.fund_symbol, member.username
					FROM ".$fund_stratification_basic_table." strat, ".$fund_table." fund, ".$members_table." member
					WHERE strat.stockSymbol = :stockSymbol
					AND strat.fund_id = fund.fund_id
					AND member.member_id = fund.member_id
					ORDER BY strat.fund_id ASC
				";
				try{
					$rsFunds = $mLink->prepare($query);
					$aValues = array(
						':stockSymbol' 	=> $oldSymbol
					);
					$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
					//echo $preparedQuery;die();
					$rsFunds->execute($aValues);
				}
				catch(PDOException $error){
					// Log any error
					file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				}

				//Create an array to hold the fund IDs
				$aFundIDs = array();
				while($funds = $rsFunds->fetch(PDO::FETCH_ASSOC)){

					// Push the fund ID onto the array
					$aFundIDs[] = $funds['fund_id'];

					// Assign values
					$fundID 	= $funds['fund_id'];
					$fundSymbol	= $funds['fund_symbol'];
					$aFundID 	= explode('-',$fundID);
					$memberID	= $aFundID[0];
					$username	= $funds['username'];

					// Delete all the old trades (we're replacing them with new ones with the new symbol)
					$query = "
						DELETE FROM ".$fund_trades_table."
						WHERE fund_id = :fund_id
						AND stockSymbol = :stockSymbol
					";
					try{
						$rsDelete = $mLink->prepare($query);
						$aValues = array(
							':stockSymbol' 	=> $oldSymbol,
							':fund_id'		=> $fundID
						);
						$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
						//echo $preparedQuery;//die();
						$rsDelete->execute($aValues);
					}
					catch(PDOException $error){
						// Log any error
						file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}

					// Delete all the old stratification records too.
					$query = "
						DELETE FROM ".$fund_stratification_basic_table."
						WHERE fund_id = :fund_id
						AND stockSymbol = :stockSymbol
					";
					try{
						$rsDelete = $mLink->prepare($query);
						$aValues = array(
							':stockSymbol' 	=> $oldSymbol,
							':fund_id'		=> $fundID
						);
						$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
						//echo $preparedQuery;//die();
						$rsDelete->execute($aValues);
					}
					catch(PDOException $error){
						// Log any error
						file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}

					// Delete all the position details for this stock.
					$query = "
						DELETE FROM ".$fund_positions_details_table."
						WHERE fund_id = :fund_id
						AND stockSymbol = :stockSymbol
					";
					try{
						$rsDelete = $mLink->prepare($query);
						$aValues = array(
							':stockSymbol' 	=> $oldSymbol,
							':fund_id'		=> $fundID
						);
						$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
						//echo $preparedQuery;//die();
						$rsDelete->execute($aValues);
					}
					catch(PDOException $error){
						// Log any error
						file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}

                                        // Finally, update (don;t delete!) any labels the member might have placed on the changed stock (by symbol)
                                        $query = "
                                                UPDATE ".$fund_positions_labels_table."
                                                SET stock_symbol = :newSymbol
                                                WHERE fund_id = :fund_id
                                                AND stock_symbol = :oldSymbol
                                       ";
                                        try{
                                                $rsUpdate = $mLink->prepare($query);
                                                $aValues = array(
                                                        ':oldSymbol'  => $oldSymbol,
                                                        ':newSymbol'  => $oldSymbol,
                                                        ':fund_id'              => $fundID
                                                );
                                                $preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
                                                //echo $preparedQuery;//die();
                                                $rsUpdate->execute($aValues);
                                        }
                                        catch(PDOException $error){
                                                // Log any error
                                                file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
                                        }
					// Get new trade history
					$query = 'tradesForPosition|0|'.$username.'|'.$fundID.'|'.$fundSymbol.'|'.$newSymbol;
					// Execute the query call to submit the request
					exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

					// Increment port value
					if($port >= $portCeil){
						$port = $portFloor;
					}else{
						$port++;
					}

					// Get new position details
					$query = 'positionInfo|'.$username.'|'.$fundID.'|'.$fundSymbol.'|'.$newSymbol;
					// Execute the query call to submit the request
					exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

					// Increment port value
					if($port >= $portCeil){
						$port = $portFloor;
					}else{
						$port++;
					}

				} //END while

				// Store results in a scratch table
				$query = "
					INSERT INTO ".$ca_affected_funds_table." (
						ca_type,
						fund_ids,
						old_stock_symbol,
						stock_symbol,
						timestamp
					)VALUES(
						'ticker_change',
						:fund_ids,
						:old_stock_symbol,
						:stock_symbol,
						UNIX_TIMESTAMP()
					)
				";
				try{
					$rsInsert = $mLink->prepare($query);
					$aValues = array(
						':old_stock_symbol' 	=> $oldSymbol,
						':stock_symbol'			=> $newSymbol,
						':fund_ids'				=> implode('|',$aFundIDs)
					);
					$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
					$rsInsert->execute($aValues);
				}
				catch(PDOException $error){
					// Log any error
					file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				}

			} //END while

	} // END switch

} // END for
?>
