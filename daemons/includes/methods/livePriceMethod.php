<?php
/*
This include processes the returned XML for the livePrice method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>livePrice</method>
   <trans_ID>999</trans_ID>
   <fund_ID>1-1</fund_ID>
   <nav>10.5906593959</nav>
   <stockValue>1059664.36682</stockValue>
   <cashValue>-598.427236973</cashValue>
   <totalValue>1059065.93959</totalValue>
   <todayReturn>0.447487683092</todayReturn>
   <shares>100000.0</shares>
   <violatesDiversification25>1</violatesDiversification25>
   <violatesDiversification10>0</violatesDiversification10>
   <violatesCash35>0</violatesCash35>
   <isInMargin>0</isInMargin>
   <sp500TodayReturn>0.0402007057457</sp500TodayReturn>
   <nasdaqTodayReturn>0.15588545214</nasdaqTodayReturn>
</results>
*/

						// Set main level vars
						$transID					= $xml->trans_ID;
						$fundID						= $xml->fund_ID;
						$NAV						= $xml->nav;
						$stockValue					= $xml->stockValue;
						$cashValue					= $xml->cashValue;
						$totalValue					= $xml->totalValue;
						$todayReturn				= $xml->todayReturn;
						$shares						= $xml->shares;
						$violatesDiversification25	= $xml->violatesDiversification25;
						$violatesDiversification10	= $xml->violatesDiversification10;
						$violatesCash35				= $xml->violatesCash35;
						$isInMargin					= $xml->isInMargin;
						$sp500TodayReturn			= $xml->sp500TodayReturn;
						$nasdaqTodayReturn			= $xml->nasdaqTodayReturn;
						$aContents					= explode("?>", $contents);

						// Check to see if the fund is already in the table
						$query =
							"SELECT *
							 FROM ".$fund_liveprice_table."
							 WHERE fund_id = :fund_id
						";
						try{
							$rsFund = $mLink->prepare($query);
							$aValues = array(
								':fund_id'	=>	$fundID
							);
							$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
							//echo $preparedQuery."\n";
							$rsFund->execute($aValues);
						}

						catch(PDOException $error){
							// Log any error
							file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
						}

						if ($rsFund->rowCount() < 1){

							// WOW!  It's not there (SURPRISE!)  Let's add it.
							$query = "
								INSERT INTO ".$fund_liveprice_table." (
									fund_ID,
									timestamp,
									nav,
									stockValue,
									cashValue,
									totalValue,
									todayReturn,
									shares,
									violatesDiversification25,
									violatesDiversification10,
									violatesCash35,
									isInMargin,
									sp500TodayReturn,
									nasdaqTodayReturn
								)VALUES(
									:fund_id,
									UNIX_TIMESTAMP(),
									:nav,
									:stock_value,
									:cash_value,
									:total_value,
									:today_return,
									:shares,
									:violates_diversification_25,
									:violates_diversification_10,
									:violates_cash_35,
									:is_in_margin,
									:sp500_today_return,
									:nasdaq_today_return
								)
							";
							try{
								$rsInsert = $mLink->prepare($query);
								$aValues = array(
									':fund_id'						=> $fundID,
									':nav'							=> $NAV,
									':stock_value'					=> $stockValue,
									':cash_value'					=> $cashValue,
									':total_value'					=> $totalValue,
									':today_return'					=> $todayReturn,
									':shares'						=> $shares,
									':violates_diversification_25'	=> $violatesDiversification25,
									':violates_diversification_10'	=> $violatesDiversification10,
									':violates_cash_35'				=> $violatesCash35,
									':is_in_margin'					=> $isInMargin,
									':sp500_today_return'			=> $sp500TodayReturn,
									':nasdaq_today_return'			=> $nasdaqTodayReturn
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

							// Update it
							$query =
								"UPDATE ".$fund_liveprice_table."
								 SET	fund_id						= :fund_id,
										timestamp					= UNIX_TIMESTAMP(),
										nav							= :nav,
										stockValue					= :stock_value,
										cashValue					= :cash_value,
										totalValue					= :total_value,
										todayReturn					= :today_return,
										shares						= :shares,
										violatesDiversification25	= :violates_diversification_25,
										violatesDiversification10	= :violates_diversification_10,
										violatesCash35				= :violates_cash_35,
										isInMargin					= :is_in_margin,
										sp500TodayReturn			= :sp500_today_return,
										nasdaqTodayReturn			= :nasdaq_today_return,
										legacy						= 1
								 WHERE fund_id = :fund_id
							";
							try{
								$rsUpdate = $mLink->prepare($query);
								$aValues = array(
									':fund_id'						=> $fundID,
									':nav'							=> $NAV,
									':stock_value'					=> $stockValue,
									':cash_value'					=> $cashValue,
									':total_value'					=> $totalValue,
									':today_return'					=> $todayReturn,
									':shares'						=> $shares,
									':violates_diversification_25'	=> $violatesDiversification25,
									':violates_diversification_10'	=> $violatesDiversification10,
									':violates_cash_35'				=> $violatesCash35,
									':is_in_margin'					=> $isInMargin,
									':sp500_today_return'			=> $sp500TodayReturn,
									':nasdaq_today_return'			=> $nasdaqTodayReturn
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