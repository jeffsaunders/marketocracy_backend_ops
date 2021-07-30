<?php
/*
This include updates the APITransactions log with the details of the results.
Called from daemons/includes/resultsProcessing.php
*/
/*
						// UPDATE the log_transactions_api table
						$query = "	UPDATE ".$legacy_api_trans_table."
									SET	processing			= 0,
										xml_recv			= :query,
										xml_recv_timestamp	= UNIX_TIMESTAMP(),
										completion_timestamp = UNIX_TIMESTAMP()
									WHERE trans_id			= :transID
						";
						try{
							$rsUpdate = $mLink->prepare($query);
							$aValues = array(
							   	':query'		=> addslashes($aContents[1]),
							   	':transID'		=> $transID
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsUpdate->execute($aValues);
						}
						catch(PDOException $error){
							// Log any error
							file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}
*/

// PDO is occasionally misunderstanding the content of the returned XML and not formatting it properly for the query.
// In particular, it's not always wrapping the XML in quotes so the UPDATE fails.
// So, instead of the usual substitution I'm, essentially, hard-coding the value right in the query with the quotes instead.

						// UPDATE the log_transactions_api table
						$query = "	UPDATE ".$legacy_api_trans_table."
									SET	processing			= 0,
										xml_recv			= '".addslashes($aContents[1])."',
										xml_recv_timestamp	= UNIX_TIMESTAMP(),
										completion_timestamp = UNIX_TIMESTAMP()
									WHERE trans_id			= :transID
						";
						try{
							$rsUpdate = $mLink->prepare($query);
							$aValues = array(
							   	':transID'		=> $transID
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsUpdate->execute($aValues);
						}
						catch(PDOException $error){
							// Log any error
							file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

?>