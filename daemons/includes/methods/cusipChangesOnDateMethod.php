<?php
/*
This include processes the returned XML for the cusipChangesOnDate method (CAProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>cusipChangesOnDate</method>
   <trans_ID>1</trans_ID>
   <date>20151102</date>
   <actions>
      <oneAction>
         <symbol>HPQ</symbol>
         <oldCusip>428236103</oldCusip>
         <newCusip>40434L105</newCusip>
      </oneAction>
      <oneAction>
         <symbol>NRF</symbol>
         <oldCusip>66704R704</oldCusip>
         <newCusip>66704R803</newCusip>
      </oneAction>
   </actions>
</results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$date		= $xml->date;
								$unixDate	= mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4));
								$aActions	= $xml->actions;
								$aContents	= explode("?>", $contents);

								// loop through each day and assign values
								foreach($aActions->oneAction as $key=>$aOneAction){

									// Set oneAction level vars
									$symbol		= $aOneAction->symbol;
									$oldCusip	= $aOneAction->oldCusip;
									$newCusip	= $aOneAction->newCusip;

									// Get company name and ID
									// Sets $company_name and $company_id values
									include("/var/www/html/daemons/includes/resultsCheckCompany.php");

									// Delete existing values for this type of CA for this company on this date (if there are any)
									$query = "
										DELETE FROM ".$stock_corporate_actions_table."
										WHERE date		= :date
										AND company_id	= :company_id
										AND symbol		= :symbol
										AND method		= :method
									";
									try{
										$rsDelete = $mLink->prepare($query);
										$aValues = array(
											':date'			=> $date,
											':company_id'	=> $company_id,
											':symbol'		=> $symbol,
											':method'		=> $method
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsDelete->execute($aValues);
									}
									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}

									// Insert new record

									$query = "
										INSERT INTO ".$stock_corporate_actions_table." (
											date,
											unix_date,
											method,
											company_id,
											company_name,
											symbol,
											oldCUSIP,
											newCUSIP,
											timestamp
										)VALUES(
											:date,
											:unix_date,
											:method,
											:company_id,
											:company_name,
											:symbol,
											:old_cusip,
											:new_cusip,
											UNIX_TIMESTAMP()
										)
									";
									try{
										$rsInsert = $mLink->prepare($query);
										$aValues = array(
											':date'			=> $date,
											':unix_date'	=> $unixDate,
											':method'		=> $method,
											':company_id'	=> $company_id,
											':company_name'	=> $company_name,
											':symbol'		=> $symbol,
											':old_cusip'	=> $oldCusip,
											':new_cusip'	=> $newCusip
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsInsert->execute($aValues);
									}
									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}

								}

?>