<?php
/*
This include processes the returned XML for the positionDetail method (tradeProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>positionDetail</method>
   <trans_ID>0</trans_ID>
   <date>20140601</date>
   <positionsList>
      <position>
         <stockSymbol>MSFT</stockSymbol>
         <name>MICROSOFT CORP</name>
         <shares>1000</shares>
         <dividends>0.0</dividends>
         <value>40940.0</value>
         <ratio>0.0407898339071</ratio>
         <price>40.94</price>
      </position>
      <position>
         <stockSymbol>GOOGL</stockSymbol>
         <name>Google</name>
         <shares>200</shares>
         <dividends>0.0</dividends>
         <value>114330.0</value>
         <ratio>0.113910642663</ratio>
         <price>571.65</price>
      </position>
      <position>
         <stockSymbol>F</stockSymbol>
         <name>FORD MOTOR CO</name>
         <shares>10000</shares>
         <dividends>0.0</dividends>
         <value>164400.0</value>
         <ratio>0.163796988137</ratio>
         <price>16.44</price>
      </position>
   </positionsList>
   <fund_ID>999-1</fund_ID>
</results>

*/

						// This method actually writes to the members_fund_positions table (not _details), despite the naming confusion

						// Set main level vars
						$transID		= $xml->trans_ID;
						$fundID			= $xml->fund_ID;
						$date			= $xml->date;
						$aPositionsList	= $xml->positionsList;
						$aContents		= explode("?>", $contents);

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
								file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
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
							file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
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
								file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
							}

						} // End loop for $aPositionsList
?>