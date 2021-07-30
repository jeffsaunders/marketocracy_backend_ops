<?php
/*
This include processes the returned XML for the newFund method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>newFund</method><trans_ID>999</trans_ID><fundKey>D48334355614259BC0A86FA7</fundKey><fund_ID>9999-1</fund_ID></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$fundID		= $xml->fund_ID;
								$fundKey	= $xml->fundKey;
								$aContents	= explode("?>", $contents);

								// Now insert the key into the new fund's record
								$query =
									"UPDATE ".$fund_table."
									 SET	fb_primarykey = :fund_key
									 WHERE	fund_id = :fund_id
								";
								try{
									$rsUpdate = $mLink->prepare($query);
									$aValues = array(
										':fund_key'	=> 'X\''.strtoupper($fundKey).'\'',
										':fund_id' 	=>	$fundID
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