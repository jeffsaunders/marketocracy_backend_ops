<?php
/*
This include processes the returned XML for the priceRun method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>priceRun</method>
   <trans_ID>999</trans_ID>
   <fund_ID>9999-1</fund_ID>
   <day>
      <date>20010511</date>
      <startCash>88373.8476121</startCash>
      <positionsValue>942572.47</positionsValue>
      <cashValue>88327.4786701</cashValue>
      <totalValue>1030899.94867</totalValue>
      <price>10.3089994867</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>10.2847064205</interest>
      <fees>56.4876684203</fees>
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
   <day>
      <date>20010512</date>
      <startCash>88327.4786701</startCash>
      <positionsValue>942572.47</positionsValue>
      <cashValue>88281.2757081</cashValue>
      <totalValue>1030853.74571</totalValue>
      <price>10.3085374571</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>10.2793266235</interest>
      <fees>56.4851367511</fees>
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
   <day>
      <date>20010513</date>
      <startCash>88281.2757081</startCash>
      <positionsValue>942572.47</positionsValue>
      <cashValue>88235.069898</cashValue>
      <totalValue>1030807.5399</totalValue>
      <price>10.308075399</price>
      <shares>100000.0</shares>
      <inFlow>0.0</inFlow>
      <outFlow>0.0</outFlow>
      <netFlow>0.0</netFlow>
      <tradeBuys>0.0</tradeBuys>
      <tradeSells>0.0</tradeSells>
      <tradeValue>0.0</tradeValue>
      <tradeRatio>9.223372039e+18</tradeRatio>
      <interest>10.273946495</interest>
      <fees>56.4826049259</fees>
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