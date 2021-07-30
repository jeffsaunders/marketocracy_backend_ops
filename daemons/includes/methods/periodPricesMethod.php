<?php
/*
This include processes the returned XML for the periodPrices method (fundpriceProcessing API).
Essentially identical to the priceRun method processing code.  Separate in case it diverges in the future.
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>periodPrices</method>
   <trans_ID>999</trans_ID>
   <fund_ID>9999-1</fund_ID>
   <day>
      <date>20140331</date>
      <startCash>253996.538032</startCash>
      <positionsValue>736438.9</positionsValue>
      <cashValue>253975.421509</cashValue>
      <totalValue>990414.321509</totalValue>
      <price>9.90414321509</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>6.95823072626</interest>
      <fees>54.2692778909</fees>
      <dividends>26.4500007629</dividends>
      <compliance>0</compliance>
      <secCompliance>0</secCompliance>
      <welterCompliance>0</welterCompliance>
      <featherCompliance>0</featherCompliance>
      <missingPriceCount>0</missingPriceCount>
      <violatesCash35>0</violatesCash35>
      <violatesDiversification25>0</violatesDiversification25>
      <tradedAbove25>0</tradedAbove25>
      <violatesDiversification10>1</violatesDiversification10>
   </day>
   <day>
      <date>20140630</date>
      <startCash>220087.839308</startCash>
      <positionsValue>808244.59</positionsValue>
      <cashValue>220065.813047</cashValue>
      <totalValue>1028310.40305</totalValue>
      <price>10.2831040305</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>6.02920035744</interest>
      <fees>56.3457755094</fees>
      <dividends>28.2000007629</dividends>
      <compliance>0</compliance>
      <secCompliance>0</secCompliance>
      <welterCompliance>0</welterCompliance>
      <featherCompliance>0</featherCompliance>
      <missingPriceCount>0</missingPriceCount>
      <violatesCash35>0</violatesCash35>
      <violatesDiversification25>0</violatesDiversification25>
      <tradedAbove25>0</tradedAbove25>
      <violatesDiversification10>1</violatesDiversification10>
   </day>
   <day>
      <date>20140930</date>
      <startCash>261083.716623</startCash>
      <positionsValue>775372.36</positionsValue>
      <cashValue>261063.424406</cashValue>
      <totalValue>1036435.78441</totalValue>
      <price>10.3643578441</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>7.15242258648</interest>
      <fees>56.7910018853</fees>
      <dividends>29.375</dividends>
      <compliance>1</compliance>
      <secCompliance>1</secCompliance>
      <welterCompliance>1</welterCompliance>
      <featherCompliance>1</featherCompliance>
      <missingPriceCount>0</missingPriceCount>
      <violatesCash35>0</violatesCash35>
      <violatesDiversification25>0</violatesDiversification25>
      <tradedAbove25>0</tradedAbove25>
      <violatesDiversification10>0</violatesDiversification10>
   </day>
   <day>
      <date>20141231</date>
      <startCash>96058.3177544</startCash>
      <positionsValue>795372.66</positionsValue>
      <cashValue>96011.8985276</cashValue>
      <totalValue>891384.558528</totalValue>
      <price>8.91384558528</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>2.63046297336</interest>
      <fees>48.8429895084</fees>
      <dividends>0.0</dividends>
      <compliance>1</compliance>
      <secCompliance>1</secCompliance>
      <welterCompliance>1</welterCompliance>
      <featherCompliance>1</featherCompliance>
      <missingPriceCount>0</missingPriceCount>
      <violatesCash35>0</violatesCash35>
      <violatesDiversification25>0</violatesDiversification25>
      <tradedAbove25>0</tradedAbove25>
      <violatesDiversification10>0</violatesDiversification10>
   </day>
</results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$fundID		= $xml->fund_ID;
								$aDays		= $xml->day;
								$aContents	= explode("?>", $contents);

								// loop through each day and assign values
								foreach($aDays as $key=>$day){

									// Set trade level vars
									$date		  				= $day->date;
									$unixDate	   				= mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4));
									$startCash	   				= $day->startCash;
									$positionsValue				= $day->positionsValue;
									$cashValue	  				= $day->cashValue;
									$totalValue	  				= $day->totalValue;
									$price		 				= $day->price;
									$shares						= $day->shares;
									$inFlow	   					= $day->inFlow;
									$outFlow  					= $day->outFlow;
									$netFlow					= $day->netFlow;
									$tradeBuys					= $day->tradeBuys;
									$tradeSells					= $day->tradeSells;
									$tradeValue					= $day->tradeValue;
									$tradeRatio					= $day->tradeRatio;
									$interest					= $day->interest;
									$fees						= $day->fees;
									$dividends					= $day->dividends;
									$compliance					= $day->compliance;
									$secCompliance				= $day->secCompliance;
									$welterCompliance			= $day->welterCompliance;
									$featherCompliance			= $day->featherCompliance;
									$missingPriceCount			= $day->missingPriceCount;
									$violatesCash35				= $day->violatesCash35;
									$violatesDiversification25	= $day->violatesDiversification25;
									$tradedAbove25				= $day->tradedAbove25;
									$violatesDiversification10	= $day->violatesDiversification10;

									// Delete existing values for fundID on given date (if there are any)
									$query = "
										DELETE FROM ".$fund_pricing_table."
										WHERE fund_id	= :fund_id
										AND date		= :date
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

									// Insert new record
									$query = "
										INSERT INTO ".$fund_pricing_table." (
											fund_ID,
											timestamp,
											date,
											unix_date,
											startCash,
											positionsValue,
											cashValue,
											totalValue,
											price,
											shares,
											inFlow,
											outFlow,
											netFlow,
											tradeBuys,
											tradeSells,
											tradeValue,
											tradeRatio,
											interest,
											fees,
											dividends,
											compliance,
											secCompliance,
											welterCompliance,
											featherCompliance,
											missingPriceCount,
											violatesCash35,
											violatesDiversification25,
											tradedAbove25,
											violatesDiversification10
										)VALUES(
											:fund_id,
											UNIX_TIMESTAMP(),
											:date,
											:unix_date,
											:start_cash,
											:positions_value,
											:cash_value,
											:total_value,
											:price,
											:shares,
											:in_flow,
											:out_flow,
											:net_flow,
											:trade_buys,
											:trade_sells,
											:trade_value,
											:trade_ratio,
											:interest,
											:fees,
											:dividends,
											:compliance,
											:sec_compliance,
											:welter_compliance,
											:feather_compliance,
											:missing_price_count,
											:violates_cash_35,
											:violates_diversification_25,
											:traded_above_25,
											:violates_diversification_10
										)
									";
									try{
										$rsInsert = $mLink->prepare($query);
										$aValues = array(
											':fund_id'						=> $fundID,
											':date'							=> $date,
											':unix_date'					=> $unixDate,
											':start_cash'					=> $startCash,
											':positions_value'				=> $positionsValue,
											':cash_value'					=> $cashValue,
											':total_value'					=> $totalValue,
											':price'						=> $price,
											':shares'						=> $shares,
											':in_flow'						=> $inFlow,
											':out_flow'						=> $outFlow,
											':net_flow'						=> $netFlow,
											':trade_buys'					=> $tradeBuys,
											':trade_sells'					=> $tradeSells,
											':trade_value'					=> $tradeValue,
											':trade_ratio'					=> $tradeRatio,
											':interest'						=> $interest,
											':fees'							=> $fees,
											':dividends'					=> $dividends,
											':compliance'					=> $compliance,
											':sec_compliance'				=> $secCompliance,
											':welter_compliance'			=> $welterCompliance,
											':feather_compliance'			=> $featherCompliance,
											':missing_price_count'			=> $missingPriceCount,
											':violates_cash_35'				=> $violatesCash35,
											':violates_diversification_25'	=> $violatesDiversification25,
											':traded_above_25'				=> $violatesCash35,
											':violates_diversification_10'	=> $violatesDiversification10
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
