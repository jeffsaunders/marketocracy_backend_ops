<?php
/*
This include processes the returned XML for the managerPassword method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>managerPassword</method><trans_ID>999</trans_ID><login>jeffsaunders</login><password>n0thing!</password></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$username	= $xml->login;
								$password	= $xml->password;
								$aContents	= explode("?>", $contents);

								// Check to see if the member exists in the clear passwords table
								$query =
									"SELECT *
									 FROM clear_passwords
									 WHERE	username = :username
								";
								try{
									$rsExists = $mLink->prepare($query);
									$aValues = array(
										':username'	=>	$username
									);
									$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
									//echo $preparedQuery."\n";
									$rsExists->execute($aValues);
								}

								catch(PDOException $error){
									// Log any error
									file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
								}

								if ($rsExists->rowCount() < 1){

									// Insert the username and password (clear version)
									$query = "
										INSERT INTO clear_passwords (
											username,
											password
										)VALUES(
											:username,
											:password
										)
									";
									try{
										$rsInsert = $mLink->prepare($query);
										$aValues = array(
											':username'	=> $username,
											':password'	=> $password
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsInsert->execute($aValues);
									}
									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}

								}else{

									// Update the member's password (clear version)
									$query =
										"UPDATE clear_passwords
										 SET	password = :password
										 WHERE	username = :username
									";
									try{
										$rsUpdate = $mLink->prepare($query);
										$aValues = array(
											':username'	=>	$username,
											':password'	=>	$password
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsUpdate->execute($aValues);
									}

									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}
								}

?>