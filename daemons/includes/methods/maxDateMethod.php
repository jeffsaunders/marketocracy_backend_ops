<?php
/*
This include processes the returned XML for the maxDate method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>maxDate</method><trans_ID>999</trans_ID><date>20151011</date></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$maxDate	= $xml->date;
								$aContents	= explode("?>", $contents);

								// Write maxDate to members_fund_maxdate (overwrite old value)
								$query =
									"UPDATE ".$fund_maxdate_table."
									 SET 	timestamp = UNIX_TIMESTAMP(),
									 		maxdate	= :max_date
									 WHERE	1
								";
								try{
									$rsUpdate = $mLink->prepare($query);
									$aValues = array(
										':max_date'		=> $maxDate
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