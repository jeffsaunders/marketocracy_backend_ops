<?php
/*
This include processes the returned XML for the untrade method (tradeProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>untrade</method><trans_ID>999</trans_ID><ticketKey>3D6F0FBE5613DA27C0A86FA7</ticketKey></results>
*/

						// Set main level vars
						$transID	= $xml->trans_ID;
						$ticketKey	= $xml->ticketKey;
						$aContents 	= explode("?>", $contents);

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
							file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						// Assign the values
						$ticket = $rsTicketInfo->fetch(PDO::FETCH_ASSOC);

						// Build API query to pull new trade history
						$query = "tradesForFund|0|".$ticket['username']."|".$ticket['fund_id']."|".$ticket['fund_symbol']."|".$ticket['closed'];
						//echo $query."\n"; die();

						// Set the port number for the API call
						if ($aAPI[0] == "api"){
							$port = rand(52000, 52099);
						}else{
							$port = rand(52100, 52499);
						}

						// Execute the query call to submit the request
						exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

?>