<?php
/*
This include processes the returned XML for the tradesForFund method (tradeProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>tradesForFund</method>
   <trans_ID>999</trans_ID>
   <fund_ID>999-1</fund_ID>
   <startDate>20150920</startDate>
   <positionsList>
      <position>
         <stockSymbol>MSFT</stockSymbol>
      </position>
      <position>
         <stockSymbol>GOOGL</stockSymbol>
      </position>
      <position>
         <stockSymbol>F</stockSymbol>
      </position>
      <position>
         <stockSymbol>S</stockSymbol>
      </position>
      <position>
         <stockSymbol>CHGS</stockSymbol>
      </position>
      <position>
         <stockSymbol>SALM</stockSymbol>
      </position>
      <position>
         <stockSymbol>JCP</stockSymbol>
      </position>
      <position>
         <stockSymbol>TSLA</stockSymbol>
      </position>
      <position>
         <stockSymbol>GOOG</stockSymbol>
      </position>
      <position>
         <stockSymbol>AAPL</stockSymbol>
      </position>
      <position>
         <stockSymbol>LUV</stockSymbol>
      </position>
      <position>
         <stockSymbol>NFLX</stockSymbol>
      </position>
      <position>
         <stockSymbol>RSHCQ</stockSymbol>
      </position>
      <position>
         <stockSymbol>UPS</stockSymbol>
      </position>
      <position>
         <stockSymbol>AMZN</stockSymbol>
      </position>
      <position>
         <stockSymbol>TTM</stockSymbol>
         <trade>
            <opened>20150923</opened>
			<openedTime>14:51:08</openedTime>
            <closed>20150923</closed>
			<closedTime>13:11:35</closedTime>
            <sharesOrdered>1000.0</sharesOrdered>
            <sharesFilled>1000.0</sharesFilled>
            <price>23.4442</price>
            <limit />
            <createdByCA>0</createdByCA>
            <net>23444.19</net>
            <secFee>0.41</secFee>
            <commission>50.0</commission>
            <buyOrSell>Sell</buyOrSell>
            <dayOrGTC>Day</dayOrGTC>
            <ticketKey>08A700E8560225D2C0A86FA6</ticketKey>
            <comment />
         </trade>
      </position>
      <position>
         <stockSymbol>GROG</stockSymbol>
      </position>
      <position>
         <stockSymbol>PYPL</stockSymbol>
      </position>
      <position>
         <stockSymbol>PYPL</stockSymbol>
         <trade>
            <opened>20150923</opened>
			<openedTime>14:51:08</openedTime>
            <closed>20150923</closed>
			<closedTime>13:11:35</closedTime>
            <sharesOrdered>1000.0</sharesOrdered>
            <sharesFilled>1000.0</sharesFilled>
            <price>33.4394</price>
            <limit />
            <createdByCA>0</createdByCA>
            <net>33439.41</net>
            <secFee>0.59</secFee>
            <commission>50.0</commission>
            <buyOrSell>Sell</buyOrSell>
            <dayOrGTC>Day</dayOrGTC>
            <ticketKey>089600E8560225D1C0A86FA6</ticketKey>
            <comment />
         </trade>
      </position>
      <position>
         <stockSymbol>AAL</stockSymbol>
      </position>
      <position>
         <stockSymbol>DIS</stockSymbol>
         <trade>
            <opened>20150923</opened>
			<openedTime>14:51:08</openedTime>
            <closed>20150923</closed>
			<closedTime>13:11:35</closedTime>
            <sharesOrdered>68.0</sharesOrdered>
            <sharesFilled>68.0</sharesFilled>
            <price>102.27</price>
            <limit />
            <createdByCA>0</createdByCA>
            <net>6954.36</net>
            <secFee>0.0</secFee>
            <commission>3.4</commission>
            <buyOrSell>Buy</buyOrSell>
            <dayOrGTC>Day</dayOrGTC>
            <ticketKey>08B800E856022623C0A86FA6</ticketKey>
            <comment />
         </trade>
         <trade>
            <opened>20150923</opened>
			<openedTime>14:51:08</openedTime>
            <closed>20150923</closed>
			<closedTime>13:11:35</closedTime>
            <sharesOrdered>557.0</sharesOrdered>
            <sharesFilled>557.0</sharesFilled>
            <price>102.03</price>
            <limit />
            <createdByCA>0</createdByCA>
            <net>56830.71</net>
            <secFee>0.0</secFee>
            <commission>27.85</commission>
            <buyOrSell>Buy</buyOrSell>
            <dayOrGTC>Day</dayOrGTC>
            <ticketKey>619C010A5602B3A8C0A86FA6</ticketKey>
            <comment />
         </trade>
      </position>
   </positionsList>
</results>


*/

						// Set main level vars
						$transID		= $xml->trans_ID;
						$fundID			= $xml->fund_ID;
						$startDate		= $xml->startDate;
						$aPositionList	= $xml->positionsList;
						$startTimestamp = mktime(5, 0 ,0, substr($startDate, 4, 2), substr($startDate, 6, 2), substr($startDate, 0, 4));
						$aContents		= explode("?>", $contents);

						// Delete existing values for fundID
						$query = "
							DELETE FROM ".$fund_trades_table."
							WHERE fund_id = :fund_id
							AND unix_closed >= :start_timestamp
						";
						try{
							$rsDelete = $mLink->prepare($query);
							$aValues = array(
								':fund_id'			=> $fundID,
								':start_timestamp'	=> $startTimestamp
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
						foreach($aPositionList->position as $key=>$aPositions){

							// Set position level vars
							$stockSymbol	= $aPositions->stockSymbol;
							$aTrades 		= $aPositions->trade;

							// loop through each trade within the position level
							foreach($aTrades as $key=>$trade){

								// Set trade level vars
								$opened			= $trade->opened;
								$openedTime		= $trade->openedTime;
//								$unix_opened	= mktime(5, 0, 0, substr($opened, 4, 2), substr($opened, 6, 2), substr($opened, 0, 4));
								$unix_opened	= mktime(substr($openedTime, 0, 2), substr($openedTime, 3, 2), substr($openedTime, 6, 2), substr($opened, 4, 2), substr($opened, 6, 2), substr($opened, 0, 4));
								$closed			= $trade->closed;
								$closedTime		= $trade->closedTime;
//								$unix_closed	= mktime(5, 0, 0, substr($closed, 4, 2), substr($closed, 6, 2), substr($closed, 0, 4));
								$unix_closed	= mktime(substr($closedTime, 0, 2), substr($closedTime, 3, 2), substr($closedTime, 6, 2), substr($closed, 4, 2), substr($closed, 6, 2), substr($closed, 0, 4));
								$sharesOrdered	= $trade->sharesOrdered;
								$sharesFilled	= $trade->sharesFilled;
								$price			= $trade->price;
								$limit			= $trade->limit;
								$createdByCA	= $trade->createdByCA;
								$net			= $trade->net;
								$secFee			= $trade->secFee;
								$commission		= $trade->commission;
								$buyOrSell		= $trade->buyOrSell;
								$dayOrGTC		= $trade->dayOrGTC;
								$ticketKey		= $trade->ticketKey;
								$comment		= rawurldecode($trade->comment);

								// Insert row
								$query = "
									INSERT INTO ".$fund_trades_table." (
										fund_id,
										timestamp,
										company_id,
										stockSymbol,
										opened,
										openedTime,
										unix_opened,
										closed,
										closedTime,
										unix_closed,
										sharesOrdered,
										sharesFilled,
										price,
										`limit`,
										dayOrGTC,
										ticketKey,
										ticketStatus,
										createdByCA,
										net,
										secFee,
										commission,
										buyOrSell,
										comment
									)VALUES(
										:fund_id,
										UNIX_TIMESTAMP(),
										0,
										:stockSymbol,
										:opened,
										:openedTime,
										:unix_opened,
										:closed,
										:closedTime,
										:unix_closed,
										:sharesOrdered,
										:sharesFilled,
										:price,
										:limit,
										:dayOrGTC,
										:ticketKey,
										'closed',
										:createdByCA,
										:net,
										:secFee,
										:commission,
										:buyOrSell,
										:comment
									)
								";
								try{
									$rsInsert = $mLink->prepare($query);
									$aValues = array(
										':fund_id'			=> $fundID,
										':stockSymbol'		=> $stockSymbol,
										':opened'			=> $opened,
										':openedTime'		=> $openedTime,
										':unix_opened'		=> $unix_opened,
										':closed'			=> $closed,
										':closedTime'		=> $closedTime,
										':unix_closed'		=> $unix_closed,
										':sharesOrdered'	=> $sharesOrdered,
										':sharesFilled'		=> $sharesFilled,
										':price'			=> $price,
										':limit'			=> $limit,
										':dayOrGTC'			=> $dayOrGTC,
										':ticketKey'		=> $ticketKey,
										':createdByCA'		=> $createdByCA,
										':net'				=> $net,
										':secFee'			=> $secFee,
										':commission'		=> $commission,
										':buyOrSell'		=> $buyOrSell,
										':comment'			=> $comment
									);
									$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
									//echo $preparedQuery."\n";
									$rsInsert->execute($aValues);
								}
								catch(PDOException $error){
									// Log any error
									file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
								}

							} // End loop for $aTrades
						} // End loop for $aPositionList

?>