<?php
/*
This include processes the returned XML for the aggregateStatistics method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>aggregateStatistics</method>
   <trans_ID>999</trans_ID>
   <fund_ID>9999-1</fund_ID>
   <asOfDate>20140601</asOfDate>
   <lastTradeViolationDate />
   <lastQuarterEndSECDisqualificationDate>20140630</lastQuarterEndSECDisqualificationDate>
   <effectiveInceptionDate>20131123</effectiveInceptionDate>
   <MTDReturn>-0.00487801560861</MTDReturn>
   <QTDReturn>1.33955426532</QTDReturn>
   <YTDReturn>0.472694589223</YTDReturn>
   <sp500MTDReturn>0.0</sp500MTDReturn>
   <sp500QTDReturn>2.73614834912</sp500QTDReturn>
   <sp500YTDReturn>4.06901253003</sp500YTDReturn>
   <nasdaqMTDReturn>0.0</nasdaqMTDReturn>
   <nasdaqQTDReturn>1.03905939285</nasdaqQTDReturn>
   <nasdaqYTDReturn>1.58095479805</nasdaqYTDReturn>
   <returnLast10Years />
   <marginSinceInception>0.0</marginSinceInception>
   <marginPreviousQuarter>0.0</marginPreviousQuarter>
   <marginCurrentQuarter>0.0</marginCurrentQuarter>
   <marginLast90Days>0.0</marginLast90Days>
   <compliancePercentageSinceInception>2.61780104712</compliancePercentageSinceInception>
   <compliancePercentageSinceActualInception>2.61780104712</compliancePercentageSinceActualInception>
   <compliancePercentagePreviousQuarter>0.0</compliancePercentagePreviousQuarter>
   <compliancePercentageCurrentQuarter>8.06451612903</compliancePercentageCurrentQuarter>
   <compliancePercentageLast90Days>5.55555555556</compliancePercentageLast90Days>
   <compliancePercentageLast180Days>2.77777777778</compliancePercentageLast180Days>
   <complianceSinceInception>0.0</complianceSinceInception>
   <complianceCurrentQuarter>0.0</complianceCurrentQuarter>
   <compliancePreviousQuarter>0.0</compliancePreviousQuarter>
   <compliancePrevious2Quarters>0.0</compliancePrevious2Quarters>
   <complianceLastWeek>0.0</complianceLastWeek>
   <complianceLast30Days>0.0</complianceLast30Days>
   <complianceLast90Days>0.0</complianceLast90Days>
   <complianceLast180Days>0.0</complianceLast180Days>
   <complianceLast270Days>0.0</complianceLast270Days>
   <complianceLastYear>0.0</complianceLastYear>
   <complianceLast2Years>0.0</complianceLast2Years>
   <complianceLast3Years>0.0</complianceLast3Years>
   <complianceLast4Years>0.0</complianceLast4Years>
   <complianceLast5Years>0.0</complianceLast5Years>
   <returnSinceInception>0.368145879667</returnSinceInception>
   <returnCurrentQuarter>1.33955426532</returnCurrentQuarter>
   <returnPreviousQuarter>-0.85540111399</returnPreviousQuarter>
   <returnPrevious2Quarters>0.472694589223</returnPrevious2Quarters>
   <returnLastWeek>1.19565884138</returnLastWeek>
   <returnLast30Days>2.84497665172</returnLast30Days>
   <returnLast90Days>-0.765565372673</returnLast90Days>
   <returnLast180Days>0.395648145832</returnLast180Days>
   <returnLast270Days />
   <returnLastYear />
   <returnLast2Years />
   <returnLast3Years />
   <returnLast4Years />
   <returnLast5Years />
   <bestReturn3Months>2.76853328761</bestReturn3Months>
   <bestReturn6Months>0.502283235365</bestReturn6Months>
   <bestReturn9Months />
   <bestReturn12Months />
   <bestReturn24Months />
   <worstReturn3Months>-5.6531565644</worstReturn3Months>
   <worstReturn6Months>-1.75441479167</worstReturn6Months>
   <worstReturn9Months />
   <worstReturn12Months />
   <worstReturn24Months />
   <averageReturn3Months>-0.921949610371</averageReturn3Months>
   <averageReturn6Months>-0.324565104447</averageReturn6Months>
   <averageReturn9Months />
   <averageReturn12Months />
   <averageReturn24Months />
   <volatility3Months>8.42168985201</volatility3Months>
   <volatility6Months>2.25669802704</volatility6Months>
   <volatility9Months />
   <volatility12Months />
   <volatility24Months />
   <complianceOnLastDay>0.0</complianceOnLastDay>
   <navOnLastDay>10.036814588</navOnLastDay>
   <navOnEffectiveInceptionDay>10.0</navOnEffectiveInceptionDay>
   <lineReturn30Days>4.9010123641</lineReturn30Days>
   <lineReturn90Days>-4.76420691751</lineReturn90Days>
   <lineReturn180Days>-2.87076193004</lineReturn180Days>
   <sectorCompliancePercentageSinceInception />
   <sectorCompliancePercentageCurrentQuarter />
   <sectorCompliancePercentagePreviousQuarter />
   <sectorCompliancePercentagePrevious2Quarters />
   <sectorCompliancePercentageLastWeek />
   <sectorCompliancePercentageLast30Days />
   <sectorCompliancePercentageLast90Days />
   <sectorCompliancePercentageLast180Days />
   <sectorCompliancePercentageLast270Days />
   <sectorCompliancePercentageLastYear>0.0</sectorCompliancePercentageLastYear>
   <sectorCompliancePercentageLast2Years>0.0</sectorCompliancePercentageLast2Years>
   <sectorCompliancePercentageLast3Years>0.0</sectorCompliancePercentageLast3Years>
   <sectorCompliancePercentageLast4Years>0.0</sectorCompliancePercentageLast4Years>
   <sectorCompliancePercentageLast5Years>0.0</sectorCompliancePercentageLast5Years>
   <styleCompliancePercentageCurrentQuarter />
   <styleCompliancePercentagePreviousQuarter />
   <styleCompliancePercentagePrevious2Quarters />
   <styleCompliancePercentageLastWeek />
   <styleCompliancePercentageLast30Days />
   <styleCompliancePercentageLast90Days />
   <styleCompliancePercentageLast180Days />
   <styleCompliancePercentageLast270Days />
   <styleCompliancePercentageLastYear>0.0</styleCompliancePercentageLastYear>
   <styleCompliancePercentageLast2Years>0.0</styleCompliancePercentageLast2Years>
   <styleCompliancePercentageLast3Years>0.0</styleCompliancePercentageLast3Years>
   <styleCompliancePercentageLast4Years>0.0</styleCompliancePercentageLast4Years>
   <styleCompliancePercentageLast5Years>0.0</styleCompliancePercentageLast5Years>
   <sharpeRatioSinceInception>-0.0612709034943</sharpeRatioSinceInception>
   <sharpeRatioCurrentQuarter>-0.0179156586081</sharpeRatioCurrentQuarter>
   <sharpeRatioPreviousQuarter>-0.0553837970391</sharpeRatioPreviousQuarter>
   <sharpeRatioPrevious2Quarters>-0.0522678932939</sharpeRatioPrevious2Quarters>
   <sharpeRatioLastWeek>0.346261793309</sharpeRatioLastWeek>
   <sharpeRatioLast30Days>0.0789446319115</sharpeRatioLast30Days>
   <sharpeRatioLast90Days>-0.0578560563694</sharpeRatioLast90Days>
   <sharpeRatioLast180Days>-0.0588775684494</sharpeRatioLast180Days>
   <sharpeRatioLast270Days />
   <sharpeRatioLastYear />
   <sharpeRatioLast2Years />
   <sharpeRatioLast3Years />
   <sharpeRatioLast4Years />
   <sharpeRatioLast5Years />
   <stddev5_return6Months>0.422252407538</stddev5_return6Months>
   <stddev20_return6Months>0.515537546914</stddev20_return6Months>
   <turnoverLast30Days>1.4439227024</turnoverLast30Days>
   <turnoverLast90Days>8.49532831893</turnoverLast90Days>
   <turnoverLast180Days>38.6969568227</turnoverLast180Days>
   <turnoverLastYear />
   <turnoverLast30DaysAAR>17.5677262126</turnoverLast30DaysAAR>
   <turnoverLast90DaysAAR>34.4532759601</turnoverLast90DaysAAR>
   <turnoverLast180DaysAAR>78.4688291127</turnoverLast180DaysAAR>
</results>
*/

								// Set main level vars
								$transID									= $xml->trans_ID;
								$fundID										= $xml->fund_ID;
								$asOfDate									= $xml->asOfDate;
								$unixDate					   				= mktime(5,0,0,substr($asOfDate,4,2),substr($asOfDate,6,2),substr($asOfDate,0,4));
								$lastTradeViolationDate						= $xml->lastTradeViolationDate;
								$lastQuarterEndSECDisqualificationDate		= $xml->lastQuarterEndSECDisqualificationDate;
								$effectiveInceptionDate						= $xml->effectiveInceptionDate;
								$MTDReturn									= $xml->MTDReturn;
								$QTDReturn									= $xml->QTDReturn;
								$YTDReturn									= $xml->YTDReturn;
								$sp500MTDReturn								= $xml->sp500MTDReturn;
								$sp500QTDReturn								= $xml->sp500QTDReturn;
								$sp500YTDReturn								= $xml->sp500YTDReturn;
								$nasdaqMTDReturn							= $xml->nasdaqMTDReturn;
								$nasdaqQTDReturn							= $xml->nasdaqQTDReturn;
								$nasdaqYTDReturn							= $xml->nasdaqYTDReturn;
								$returnLast10Years							= $xml->returnLast10Years;
								$marginSinceInception						= $xml->marginSinceInception;
								$marginPreviousQuarter						= $xml->marginPreviousQuarter;
								$marginCurrentQuarter  						= $xml->marginCurrentQuarter;
								$marginLast90Days							= $xml->marginLast90Days;
								$compliancePercentageSinceInception			= $xml->compliancePercentageSinceInception;
								$compliancePercentageSinceActualInception	= $xml->compliancePercentageSinceActualInception;
								$compliancePercentagePreviousQuarter		= $xml->compliancePercentagePreviousQuarter;
								$compliancePercentageCurrentQuarter			= $xml->compliancePercentageCurrentQuarter;
								$compliancePercentageLast90Days				= $xml->compliancePercentageLast90Days;
								$compliancePercentageLast180Days			= $xml->compliancePercentageLast180Days;
								$complianceSinceInception					= $xml->complianceSinceInception;
								$complianceCurrentQuarter					= $xml->complianceCurrentQuarter;
								$compliancePreviousQuarter					= $xml->compliancePreviousQuarter;
								$compliancePrevious2Quarters				= $xml->compliancePrevious2Quarters;
								$complianceLastWeek							= $xml->complianceLastWeek;
								$complianceLast30Days						= $xml->complianceLast30Days;
								$complianceLast90Days						= $xml->complianceLast90Days;
								$complianceLast180Days						= $xml->complianceLast180Days;
								$complianceLast270Days						= $xml->complianceLast270Days;
								$complianceLastYear							= $xml->complianceLastYear;
								$complianceLast2Years						= $xml->complianceLast2Years;
								$complianceLast3Years						= $xml->complianceLast3Years;
								$complianceLast4Years						= $xml->complianceLast4Years;
								$complianceLast5Years						= $xml->complianceLast5Years;
								$returnSinceInception  						= $xml->returnSinceInception;
								$returnCurrentQuarter						= $xml->returnCurrentQuarter;
								$returnPreviousQuarter						= $xml->returnPreviousQuarter;
								$returnPrevious2Quarters					= $xml->returnPrevious2Quarters;
								$returnLastWeek								= $xml->returnLastWeek;
								$returnLast30Days							= $xml->returnLast30Days;
								$returnLast90Days  							= $xml->returnLast90Days;
								$returnLast180Days							= $xml->returnLast180Days;
								$returnLast270Days							= $xml->returnLast270Days;
								$returnLastYear								= $xml->returnLastYear;
								$returnLast2Years							= $xml->returnLast2Years;
								$returnLast3Years							= $xml->returnLast3Years;
								$returnLast4Years  							= $xml->returnLast4Years;
								$returnLast5Years							= $xml->returnLast5Years;
								$bestReturn3Months							= $xml->bestReturn3Months;
								$bestReturn6Months							= $xml->bestReturn6Months;
								$bestReturn9Months							= $xml->bestReturn9Months;
								$bestReturn12Months							= $xml->bestReturn12Months;
								$bestReturn24Months							= $xml->bestReturn24Months;
								$worstReturn3Months							= $xml->worstReturn3Months;
								$worstReturn6Months							= $xml->worstReturn6Months;
								$worstReturn9Months							= $xml->worstReturn9Months;
								$worstReturn12Months						= $xml->worstReturn12Months;
								$worstReturn24Months						= $xml->worstReturn24Months;
								$averageReturn3Months						= $xml->averageReturn3Months;
								$averageReturn6Months						= $xml->averageReturn6Months;
								$averageReturn9Months						= $xml->averageReturn9Months;
								$averageReturn12Months						= $xml->averageReturn12Months;
								$averageReturn24Months						= $xml->averageReturn24Months;
								$volatility3Months							= $xml->volatility3Months;
								$volatility6Months							= $xml->volatility6Months;
								$volatility9Months							= $xml->volatility9Months;
								$volatility12Months							= $xml->volatility12Months;
								$volatility24Months							= $xml->volatility24Months;
								$complianceOnLastDay						= $xml->complianceOnLastDay;
								$navOnLastDay								= $xml->navOnLastDay;
								$navOnEffectiveInceptionDay					= $xml->navOnEffectiveInceptionDay;
								$lineReturn30Days							= $xml->lineReturn30Days;
								$lineReturn90Days							= $xml->lineReturn90Days;
								$lineReturn180Days							= $xml->lineReturn180Days;
								$sectorCompliancePercentageSinceInception	= $xml->sectorCompliancePercentageSinceInception;
								$sectorCompliancePercentageCurrentQuarter	= $xml->sectorCompliancePercentageCurrentQuarter;
								$sectorCompliancePercentagePreviousQuarter	= $xml->sectorCompliancePercentagePreviousQuarter;
								$sectorCompliancePercentagePrevious2Quarters= $xml->sectorCompliancePercentagePrevious2Quarters;
								$sectorCompliancePercentageLastWeek			= $xml->sectorCompliancePercentageLastWeek;
								$sectorCompliancePercentageLast30Days		= $xml->sectorCompliancePercentageLast30Days;
								$sectorCompliancePercentageLast90Days		= $xml->sectorCompliancePercentageLast90Days;
								$sectorCompliancePercentageLast180Days		= $xml->sectorCompliancePercentageLast180Days;
								$sectorCompliancePercentageLast270Days		= $xml->sectorCompliancePercentageLast270Days;
								$sectorCompliancePercentageLastYear			= $xml->sectorCompliancePercentageLastYear;
								$sectorCompliancePercentageLast2Years		= $xml->sectorCompliancePercentageLast2Years;
								$sectorCompliancePercentageLast3Years		= $xml->sectorCompliancePercentageLast3Years;
								$sectorCompliancePercentageLast4Years		= $xml->sectorCompliancePercentageLast4Years;
								$sectorCompliancePercentageLast5Years		= $xml->sectorCompliancePercentageLast5Years;
								$styleCompliancePercentageCurrentQuarter	= $xml->styleCompliancePercentageCurrentQuarter;
								$styleCompliancePercentagePreviousQuarter	= $xml->styleCompliancePercentagePreviousQuarter;
								$styleCompliancePercentagePrevious2Quarters	= $xml->styleCompliancePercentagePrevious2Quarters;
								$styleCompliancePercentageLastWeek			= $xml->styleCompliancePercentageLastWeek;
								$styleCompliancePercentageLast30Days		= $xml->styleCompliancePercentageLast30Days;
								$styleCompliancePercentageLast90Days		= $xml->styleCompliancePercentageLast90Days;
								$styleCompliancePercentageLast180Days		= $xml->styleCompliancePercentageLast120Days;
								$styleCompliancePercentageLast270Days		= $xml->styleCompliancePercentageLast270Days;
								$styleCompliancePercentageLastYear			= $xml->styleCompliancePercentageLastYear;
								$styleCompliancePercentageLast2Years		= $xml->styleCompliancePercentageLast2Years;
								$styleCompliancePercentageLast3Years		= $xml->styleCompliancePercentageLast3Years;
								$styleCompliancePercentageLast4Years		= $xml->styleCompliancePercentageLast4Years;
								$styleCompliancePercentageLast5Years		= $xml->styleCompliancePercentageLast5Years;
								$sharpeRatioSinceInception					= $xml->sharpeRatioSinceInception;
								$sharpeRatioCurrentQuarter					= $xml->sharpeRatioCurrentQuarter;
								$sharpeRatioPreviousQuarter					= $xml->sharpeRatioPreviousQuarter;
								$sharpeRatioPrevious2Quarters				= $xml->sharpeRatioPrevious2Quarters;
								$sharpeRatioLastWeek						= $xml->sharpeRatioLastWeek;
								$sharpeRatioLast30Days	   					= $xml->sharpeRatioLast30Days;
								$sharpeRatioLast90Days						= $xml->sharpeRatioLast90Days;
								$sharpeRatioLast180Days						= $xml->sharpeRatioLast180Days;
								$sharpeRatioLast270Days						= $xml->sharpeRatioLast270Days;
								$sharpeRatioLastYear						= $xml->sharpeRatioLastYear;
								$sharpeRatioLast2Years						= $xml->sharpeRatioLast2Years;
								$sharpeRatioLast3Years						= $xml->sharpeRatioLast3Years;
								$sharpeRatioLast4Years						= $xml->sharpeRatioLast4Years;
								$sharpeRatioLast5Years						= $xml->sharpeRatioLast5Years;
								$stddev5_return6Months 						= $xml->stddev5_return6Months;
								$stddev20_return6Months						= $xml->stddev20_return6Months;
								$turnoverLast30Days							= $xml->turnoverLast30Days;
								$turnoverLast90Days							= $xml->turnoverLast90Days;
								$turnoverLast180Days						= $xml->turnoverLast180Days;
								$turnoverLastYear							= $xml->turnoverLastYear;
								$turnoverLast30DaysAAR						= $xml->turnoverLast30DaysAAR;
								$turnoverLast90DaysAAR						= $xml->turnoverLast90DaysAAR;
								$turnoverLast180DaysAAR						= $xml->turnoverLast180DaysAAR;
		 						$aContents									= explode("?>", $contents);

								// Let's see if this is for historical purposes
								if (substr(strtoupper($fundID), -1) == "H"){
									$fundID	 = substr($fundID, 0, -1);
									$table	 = $fund_aggregate_history_table;
								}else{
									$table	 = $fund_aggregate_table;
									// Delete all the existing records - we are replacing them with fresh ones
									$query = "
										DELETE FROM ".$table."
										WHERE fund_id	= :fund_id
									";
									try{
										$rsDelete = $mLink->prepare($query);
										$aValues = array(
											':fund_id'	=> $fundID
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsDelete->execute($aValues);
									}

									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}
								}

								// Insert the stats
								$query = "
									INSERT INTO ".$table." (
										fund_ID,
										timestamp,
										asOfDate,
										unix_date,
										lastTradeViolationDate,
										lastQuarterEndSECDisqualificationDate,
										effectiveInceptionDate,
										MTDReturn,
										QTDReturn,
										YTDReturn,
										sp500MTDReturn,
										sp500QTDReturn,
										sp500YTDReturn,
										nasdaqMTDReturn,
										nasdaqQTDReturn,
										nasdaqYTDReturn,
										returnLast10Years,
										marginSinceInception,
										marginPreviousQuarter,
										marginCurrentQuarter,
										marginLast90Days,
										compliancePercentageSinceInception,
										compliancePercentageSinceActualInception,
										compliancePercentagePreviousQuarter,
										compliancePercentageCurrentQuarter,
										compliancePercentageLast90Days,
										compliancePercentageLast180Days,
										complianceSinceInception,
										complianceCurrentQuarter,
										compliancePreviousQuarter,
										compliancePrevious2Quarters,
										complianceLastWeek,
										complianceLast30Days,
										complianceLast90Days,
										complianceLast180Days,
										complianceLast270Days,
										complianceLastYear,
										complianceLast2Years,
										complianceLast3Years,
										complianceLast4Years,
										complianceLast5Years,
										returnSinceInception,
										returnCurrentQuarter,
										returnPreviousQuarter,
										returnPrevious2Quarters,
										returnLastWeek,
										returnLast30Days,
										returnLast90Days,
										returnLast180Days,
										returnLast270Days,
										returnLastYear,
										returnLast2Years,
										returnLast3Years,
										returnLast4Years,
										returnLast5Years,
										bestReturn3Months,
										bestReturn6Months,
										bestReturn9Months,
										bestReturn12Months,
										bestReturn24Months,
										worstReturn3Months,
										worstReturn6Months,
										worstReturn9Months,
										worstReturn12Months,
										worstReturn24Months,
										averageReturn3Months,
										averageReturn6Months,
										averageReturn9Months,
										averageReturn12Months,
										averageReturn24Months,
										volatility3Months,
										volatility6Months,
										volatility9Months,
										volatility12Months,
										volatility24Months,
										complianceOnLastDay,
										navOnLastDay,
										navOnEffectiveInceptionDay,
										lineReturn30Days,
										lineReturn90Days,
										lineReturn180Days,
										sectorCompliancePercentageSinceInception,
										sectorCompliancePercentageCurrentQuarter,
										sectorCompliancePercentagePreviousQuarter,
										sectorCompliancePercentagePrevious2Quarters,
										sectorCompliancePercentageLastWeek,
										sectorCompliancePercentageLast30Days,
										sectorCompliancePercentageLast90Days,
										sectorCompliancePercentageLast180Days,
										sectorCompliancePercentageLast270Days,
										sectorCompliancePercentageLastYear,
										sectorCompliancePercentageLast2Years,
										sectorCompliancePercentageLast3Years,
										sectorCompliancePercentageLast4Years,
										sectorCompliancePercentageLast5Years,
										styleCompliancePercentageCurrentQuarter,
										styleCompliancePercentagePreviousQuarter,
										styleCompliancePercentagePrevious2Quarters,
										styleCompliancePercentageLastWeek,
										styleCompliancePercentageLast30Days,
										styleCompliancePercentageLast90Days,
										styleCompliancePercentageLast180Days,
										styleCompliancePercentageLast270Days,
										styleCompliancePercentageLastYear,
										styleCompliancePercentageLast2Years,
										styleCompliancePercentageLast3Years,
										styleCompliancePercentageLast4Years,
										styleCompliancePercentageLast5Years,
										sharpeRatioSinceInception,
										sharpeRatioCurrentQuarter,
										sharpeRatioPreviousQuarter,
										sharpeRatioPrevious2Quarters,
										sharpeRatioLastWeek,
										sharpeRatioLast30Days,
										sharpeRatioLast90Days,
										sharpeRatioLast180Days,
										sharpeRatioLast270Days,
										sharpeRatioLastYear,
										sharpeRatioLast2Years,
										sharpeRatioLast3Years,
										sharpeRatioLast4Years,
										sharpeRatioLast5Years,
										stddev5_return6Months,
										stddev20_return6Months,
										turnoverLast30Days,
										turnoverLast90Days,
										turnoverLast180Days,
										turnoverLastYear,
										turnoverLast30DaysAAR,
										turnoverLast90DaysAAR,
										turnoverLast180DaysAAR
									)VALUES(
										:fundID,
										UNIX_TIMESTAMP(),
										:asOfDate,
										:unixDate,
										:lastTradeViolationDate,
										:lastQuarterEndSECDisqualificationDate,
										:effectiveInceptionDate,
										:MTDReturn,
										:QTDReturn,
										:YTDReturn,
										:sp500MTDReturn,
										:sp500QTDReturn,
										:sp500YTDReturn,
										:nasdaqMTDReturn,
										:nasdaqQTDReturn,
										:nasdaqYTDReturn,
										:returnLast10Years,
										:marginSinceInception,
										:marginPreviousQuarter,
										:marginCurrentQuarter,
										:marginLast90Days,
										:compliancePercentageSinceInception,
										:compliancePercentageSinceActualInception,
										:compliancePercentagePreviousQuarter,
										:compliancePercentageCurrentQuarter,
										:compliancePercentageLast90Days,
										:compliancePercentageLast180Days,
										:complianceSinceInception,
										:complianceCurrentQuarter,
										:compliancePreviousQuarter,
										:compliancePrevious2Quarters,
										:complianceLastWeek,
										:complianceLast30Days,
										:complianceLast90Days,
										:complianceLast180Days,
										:complianceLast270Days,
										:complianceLastYear,
										:complianceLast2Years,
										:complianceLast3Years,
										:complianceLast4Years,
										:complianceLast5Years,
										:returnSinceInception,
										:returnCurrentQuarter,
										:returnPreviousQuarter,
										:returnPrevious2Quarters,
										:returnLastWeek,
										:returnLast30Days,
										:returnLast90Days,
										:returnLast180Days,
										:returnLast270Days,
										:returnLastYear,
										:returnLast2Years,
										:returnLast3Years,
										:returnLast4Years,
										:returnLast5Years,
										:bestReturn3Months,
										:bestReturn6Months,
										:bestReturn9Months,
										:bestReturn12Months,
										:bestReturn24Months,
										:worstReturn3Months,
										:worstReturn6Months,
										:worstReturn9Months,
										:worstReturn12Months,
										:worstReturn24Months,
										:averageReturn3Months,
										:averageReturn6Months,
										:averageReturn9Months,
										:averageReturn12Months,
										:averageReturn24Months,
										:volatility3Months,
										:volatility6Months,
										:volatility9Months,
										:volatility12Months,
										:volatility24Months,
										:complianceOnLastDay,
										:navOnLastDay,
										:navOnEffectiveInceptionDay,
										:lineReturn30Days,
										:lineReturn90Days,
										:lineReturn180Days,
										:sectorCompliancePercentageSinceInception,
										:sectorCompliancePercentageCurrentQuarter,
										:sectorCompliancePercentagePreviousQuarter,
										:sectorCompliancePercentagePrevious2Quarters,
										:sectorCompliancePercentageLastWeek,
										:sectorCompliancePercentageLast30Days,
										:sectorCompliancePercentageLast90Days,
										:sectorCompliancePercentageLast180Days,
										:sectorCompliancePercentageLast270Days,
										:sectorCompliancePercentageLastYear,
										:sectorCompliancePercentageLast2Years,
										:sectorCompliancePercentageLast3Years,
										:sectorCompliancePercentageLast4Years,
										:sectorCompliancePercentageLast5Years,
										:styleCompliancePercentageCurrentQuarter,
										:styleCompliancePercentagePreviousQuarter,
										:styleCompliancePercentagePrevious2Quarters,
										:styleCompliancePercentageLastWeek,
										:styleCompliancePercentageLast30Days,
										:styleCompliancePercentageLast90Days,
										:styleCompliancePercentageLast180Days,
										:styleCompliancePercentageLast270Days,
										:styleCompliancePercentageLastYear,
										:styleCompliancePercentageLast2Years,
										:styleCompliancePercentageLast3Years,
										:styleCompliancePercentageLast4Years,
										:styleCompliancePercentageLast5Years,
										:sharpeRatioSinceInception,
										:sharpeRatioCurrentQuarter,
										:sharpeRatioPreviousQuarter,
										:sharpeRatioPrevious2Quarters,
										:sharpeRatioLastWeek,
										:sharpeRatioLast30Days,
										:sharpeRatioLast90Days,
										:sharpeRatioLast180Days,
										:sharpeRatioLast270Days,
										:sharpeRatioLastYear,
										:sharpeRatioLast2Years,
										:sharpeRatioLast3Years,
										:sharpeRatioLast4Years,
										:sharpeRatioLast5Years,
										:stddev5_return6Months,
										:stddev20_return6Months,
										:turnoverLast30Days,
										:turnoverLast90Days,
										:turnoverLast180Days,
										:turnoverLastYear,
										:turnoverLast30DaysAAR,
										:turnoverLast90DaysAAR,
										:turnoverLast180DaysAAR
									)
								";
								try{
									$rsInsert = $mLink->prepare($query);
									$aValues = array(
										':fundID'										=> $fundID,
										':asOfDate'										=> $asOfDate,
										':unixDate'										=> $unixDate,
										':lastTradeViolationDate'						=> $lastTradeViolationDate,
										':lastQuarterEndSECDisqualificationDate'		=> $lastQuarterEndSECDisqualificationDate,
										':effectiveInceptionDate'						=> $effectiveInceptionDate,
										':MTDReturn'									=> $MTDReturn,
										':QTDReturn'									=> $QTDReturn,
										':YTDReturn'									=> $YTDReturn,
										':sp500MTDReturn'								=> $sp500MTDReturn,
										':sp500QTDReturn'								=> $sp500QTDReturn,
										':sp500YTDReturn'								=> $sp500YTDReturn,
										':nasdaqMTDReturn'								=> $nasdaqMTDReturn,
										':nasdaqQTDReturn'								=> $nasdaqQTDReturn,
										':nasdaqYTDReturn'								=> $nasdaqYTDReturn,
										':returnLast10Years'							=> $returnLast10Years,
										':marginSinceInception'							=> $marginSinceInception,
										':marginPreviousQuarter'						=> $marginPreviousQuarter,
										':marginCurrentQuarter'							=> $marginCurrentQuarter,
										':marginLast90Days'								=> $marginLast90Days,
										':compliancePercentageSinceInception'			=> $compliancePercentageSinceInception,
										':compliancePercentageSinceActualInception'		=> $compliancePercentageSinceActualInception,
										':compliancePercentagePreviousQuarter'			=> $compliancePercentagePreviousQuarter,
										':compliancePercentageCurrentQuarter'			=> $compliancePercentageCurrentQuarter,
										':compliancePercentageLast90Days'				=> $compliancePercentageLast90Days,
										':compliancePercentageLast180Days'				=> $compliancePercentageLast180Days,
										':complianceSinceInception'						=> $complianceSinceInception,
										':complianceCurrentQuarter'						=> $complianceCurrentQuarter,
										':compliancePreviousQuarter'					=> $compliancePreviousQuarter,
										':compliancePrevious2Quarters'					=> $compliancePrevious2Quarters,
										':complianceLastWeek'							=> $complianceLastWeek,
										':complianceLast30Days'							=> $complianceLast30Days,
										':complianceLast90Days'							=> $complianceLast90Days,
										':complianceLast180Days'						=> $complianceLast180Days,
										':complianceLast270Days'						=> $complianceLast270Days,
										':complianceLastYear'							=> $complianceLastYear,
										':complianceLast2Years'							=> $complianceLast2Years,
										':complianceLast3Years'							=> $complianceLast3Years,
										':complianceLast4Years'							=> $complianceLast4Years,
										':complianceLast5Years'							=> $complianceLast5Years,
										':returnSinceInception'							=> $returnSinceInception,
										':returnCurrentQuarter'							=> $returnCurrentQuarter,
										':returnPreviousQuarter'						=> $returnPreviousQuarter,
										':returnPrevious2Quarters'						=> $returnPrevious2Quarters,
										':returnLastWeek'								=> $returnLastWeek,
										':returnLast30Days'								=> $returnLast30Days,
										':returnLast90Days'								=> $returnLast90Days,
										':returnLast180Days'							=> $returnLast180Days,
										':returnLast270Days'							=> $returnLast270Days,
										':returnLastYear'								=> $returnLastYear,
										':returnLast2Years'								=> $returnLast2Years,
										':returnLast3Years'								=> $returnLast3Years,
										':returnLast4Years'								=> $returnLast4Years,
										':returnLast5Years'								=> $returnLast5Years,
										':bestReturn3Months'							=> $bestReturn3Months,
										':bestReturn6Months'							=> $bestReturn6Months,
										':bestReturn9Months'							=> $bestReturn9Months,
										':bestReturn12Months'							=> $bestReturn12Months,
										':bestReturn24Months'							=> $bestReturn24Months,
										':worstReturn3Months'							=> $worstReturn3Months,
										':worstReturn6Months'							=> $worstReturn6Months,
										':worstReturn9Months'							=> $worstReturn9Months,
										':worstReturn12Months'							=> $worstReturn12Months,
										':worstReturn24Months'							=> $worstReturn24Months,
										':averageReturn3Months'							=> $averageReturn3Months,
										':averageReturn6Months'							=> $averageReturn6Months,
										':averageReturn9Months'							=> $averageReturn9Months,
										':averageReturn12Months'						=> $averageReturn12Months,
										':averageReturn24Months'						=> $averageReturn24Months,
										':volatility3Months'   							=> $volatility3Months,
										':volatility6Months'							=> $volatility6Months,
										':volatility9Months'							=> $volatility9Months,
										':volatility12Months'							=> $volatility12Months,
										':volatility24Months'							=> $volatility24Months,
										':complianceOnLastDay'							=> $complianceOnLastDay,
										':navOnLastDay'									=> $navOnLastDay,
										':navOnEffectiveInceptionDay'					=> $navOnEffectiveInceptionDay,
										':lineReturn30Days'								=> $lineReturn30Days,
										':lineReturn90Days'								=> $lineReturn90Days,
										':lineReturn180Days'							=> $lineReturn180Days,
										':sectorCompliancePercentageSinceInception'		=> $sectorCompliancePercentageSinceInception,
										':sectorCompliancePercentageCurrentQuarter'		=> $sectorCompliancePercentageCurrentQuarter,
										':sectorCompliancePercentagePreviousQuarter'  	=> $sectorCompliancePercentagePreviousQuarter,
										':sectorCompliancePercentagePrevious2Quarters'	=> $sectorCompliancePercentagePrevious2Quarters,
										':sectorCompliancePercentageLastWeek'			=> $sectorCompliancePercentageLastWeek,
										':sectorCompliancePercentageLast30Days'			=> $sectorCompliancePercentageLast30Days,
										':sectorCompliancePercentageLast90Days'			=> $sectorCompliancePercentageLast90Days,
										':sectorCompliancePercentageLast180Days'		=> $sectorCompliancePercentageLast180Days,
										':sectorCompliancePercentageLast270Days'		=> $sectorCompliancePercentageLast270Days,
										':sectorCompliancePercentageLastYear'			=> $sectorCompliancePercentageLastYear,
										':sectorCompliancePercentageLast2Years'			=> $sectorCompliancePercentageLast2Years,
										':sectorCompliancePercentageLast3Years'			=> $sectorCompliancePercentageLast3Years,
										':sectorCompliancePercentageLast4Years'			=> $sectorCompliancePercentageLast4Years,
										':sectorCompliancePercentageLast5Years'			=> $sectorCompliancePercentageLast5Years,
										':styleCompliancePercentageCurrentQuarter' 		=> $styleCompliancePercentageCurrentQuarter,
										':styleCompliancePercentagePreviousQuarter'		=> $styleCompliancePercentagePreviousQuarter,
										':styleCompliancePercentagePrevious2Quarters'	=> $styleCompliancePercentagePrevious2Quarters,
										':styleCompliancePercentageLastWeek'			=> $styleCompliancePercentageLastWeek,
										':styleCompliancePercentageLast30Days'			=> $styleCompliancePercentageLast30Days,
										':styleCompliancePercentageLast90Days'			=> $styleCompliancePercentageLast90Days,
										':styleCompliancePercentageLast180Days'			=> $styleCompliancePercentageLast180Days,
										':styleCompliancePercentageLast270Days'			=> $styleCompliancePercentageLast270Days,
										':styleCompliancePercentageLastYear'			=> $styleCompliancePercentageLastYear,
										':styleCompliancePercentageLast2Years'			=> $styleCompliancePercentageLast2Years,
										':styleCompliancePercentageLast3Years'			=> $styleCompliancePercentageLast3Years,
										':styleCompliancePercentageLast4Years'			=> $styleCompliancePercentageLast4Years,
										':styleCompliancePercentageLast5Years'			=> $styleCompliancePercentageLast5Years,
										':sharpeRatioSinceInception'					=> $sharpeRatioSinceInception,
										':sharpeRatioCurrentQuarter'					=> $sharpeRatioCurrentQuarter,
										':sharpeRatioPreviousQuarter'					=> $sharpeRatioPreviousQuarter,
										':sharpeRatioPrevious2Quarters'					=> $sharpeRatioPrevious2Quarters,
										':sharpeRatioLastWeek'							=> $sharpeRatioLastWeek,
										':sharpeRatioLast30Days'						=> $sharpeRatioLast30Days,
										':sharpeRatioLast90Days'						=> $sharpeRatioLast90Days,
										':sharpeRatioLast180Days'						=> $sharpeRatioLast180Days,
										':sharpeRatioLast270Days'						=> $sharpeRatioLast270Days,
										':sharpeRatioLastYear'							=> $sharpeRatioLastYear,
										':sharpeRatioLast2Years'						=> $sharpeRatioLast2Years,
										':sharpeRatioLast3Years'						=> $sharpeRatioLast3Years,
										':sharpeRatioLast4Years'						=> $sharpeRatioLast4Years,
										':sharpeRatioLast5Years'						=> $sharpeRatioLast5Years,
										':stddev5_return6Months'						=> $stddev5_return6Months,
										':stddev20_return6Months'						=> $stddev20_return6Months,
										':turnoverLast30Days'							=> $turnoverLast30Days,
										':turnoverLast90Days'							=> $turnoverLast90Days,
										':turnoverLast180Days'							=> $turnoverLast180Days,
										':turnoverLastYear'								=> $turnoverLastYear,
										':turnoverLast30DaysAAR'						=> $turnoverLast30DaysAAR,
										':turnoverLast90DaysAAR'						=> $turnoverLast90DaysAAR,
										':turnoverLast180DaysAAR'						=> $turnoverLast180DaysAAR
									);
									$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
									//echo $preparedQuery."\n";
									$rsInsert->execute($aValues);
								}
								catch(PDOException $error){
									// Log any error
									file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
								}

/*
Kept for convenience - that was a LOT to type in!

$fund_ID
$asOfDate
$lastTradeViolationDate
$lastQuarterEndSECDisqualificationDate
$effectiveInceptionDate
$MTDReturn
$QTDReturn
$YTDReturn
$sp500MTDReturn
$sp500QTDReturn
$sp500YTDReturn
$nasdaqMTDReturn
$nasdaqQTDReturn
$nasdaqYTDReturn
$returnLast10Years
$marginSinceInception
$marginPreviousQuarter
$marginCurrentQuarter
$marginLast90Days
$compliancePercentageSinceInception
$compliancePercentageSinceActualInception
$compliancePercentagePreviousQuarter
$compliancePercentageCurrentQuarter
$compliancePercentageLast90Days
$compliancePercentageLast180Days
$complianceSinceInception
$complianceCurrentQuarter
$compliancePreviousQuarter
$compliancePrevious2Quarters
$complianceLastWeek
$complianceLast30Days
$complianceLast90Days
$complianceLast180Days
$complianceLast270Days
$complianceLastYear
$complianceLast2Years
$complianceLast3Years
$complianceLast4Years
$complianceLast5Years
$returnSinceInception
$returnCurrentQuarter
$returnPreviousQuarter
$returnPrevious2Quarters
$returnLastWeek
$returnLast30Days
$returnLast90Days
$returnLast180Days
$returnLast270Days
$returnLastYear
$returnLast2Years
$returnLast3Years
$returnLast4Years
$returnLast5Years
$bestReturn3Months
$bestReturn6Months
$bestReturn9Months
$bestReturn12Months
$bestReturn24Months
$worstReturn3Months
$worstReturn6Months
$worstReturn9Months
$worstReturn12Months
$worstReturn24Months
$averageReturn3Months
$averageReturn6Months
$averageReturn9Months
$averageReturn12Months
$averageReturn24Months
$volatility3Months
$volatility6Months
$volatility9Months
$volatility12Months
$volatility24Months
$complianceOnLastDay
$navOnLastDay
$navOnEffectiveInceptionDay
$lineReturn30Days
$lineReturn90Days
$lineReturn180Days
$sectorCompliancePercentageSinceInception
$sectorCompliancePercentageCurrentQuarter
$sectorCompliancePercentagePreviousQuarter
$sectorCompliancePercentagePrevious2Quarters
$sectorCompliancePercentageLastWeek
$sectorCompliancePercentageLast30Days
$sectorCompliancePercentageLast90Days
$sectorCompliancePercentageLast180Days
$sectorCompliancePercentageLast270Days
$sectorCompliancePercentageLastYear
$sectorCompliancePercentageLast2Years
$sectorCompliancePercentageLast3Years
$sectorCompliancePercentageLast4Years
$sectorCompliancePercentageLast5Years
$styleCompliancePercentageCurrentQuarter
$styleCompliancePercentagePreviousQuarter
$styleCompliancePercentagePrevious2Quarters
$styleCompliancePercentageLastWeek
$styleCompliancePercentageLast30Days
$styleCompliancePercentageLast90Days
$styleCompliancePercentageLast180Days
$styleCompliancePercentageLast270Days
$styleCompliancePercentageLastYear
$styleCompliancePercentageLast2Years
$styleCompliancePercentageLast3Years
$styleCompliancePercentageLast4Years
$styleCompliancePercentageLast5Years
$sharpeRatioSinceInception
$sharpeRatioCurrentQuarter
$sharpeRatioPreviousQuarter
$sharpeRatioPrevious2Quarters
$sharpeRatioLastWeek
$sharpeRatioLast30Days
$sharpeRatioLast90Days
$sharpeRatioLast180Days
$sharpeRatioLast270Days
$sharpeRatioLastYear
$sharpeRatioLast2Years
$sharpeRatioLast3Years
$sharpeRatioLast4Years
$sharpeRatioLast5Years
$stddev5_return6Months
$stddev20_return6Months
$turnoverLast30Days
$turnoverLast90Days
$turnoverLast180Days
$turnoverLastYear
$turnoverLast30DaysAAR
$turnoverLast90DaysAAR
$turnoverLast180DaysAAR
*/
?>