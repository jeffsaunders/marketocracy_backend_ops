<?php
/*
This include processes the returned XML for the newManager method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>newManager</method><trans_ID>999</trans_ID><member_ID>99999</member_ID><managerKey>3D6F0FBE5613DA27C0A86FA7</managerKey><portfolioKey>3D720FBE5613DA27C0A86FA7</portfolioKey></results>
*/
								// Set main level vars
								$transID		= $xml->trans_ID;
								$memberID		= $xml->member_ID;
								$primaryKey		= $xml->managerKey;
								$portfolioKey	= $xml->portfolioKey;
								$aContents 		= explode("?>", $contents);

								// Now insert the key into the new member's membership record
								$query =
									"UPDATE ".$members_table."
									 SET	fb_primarykey = :primary_key,
									 		fb_portfoliokey = :portfolio_key
									 WHERE member_id = :member_id
								";
								try{
									$rsUpdate = $mLink->prepare($query);
									$aValues = array(
										':primary_key'		=> 'X\''.strtoupper($primaryKey).'\'',
										':portfolio_key'	=> 'X\''.strtoupper($portfolioKey).'\'',
										':member_id'		=>	$memberID
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