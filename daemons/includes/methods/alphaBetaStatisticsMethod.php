<?php
/*
This include processes the returned XML for the alphaBetaStatistics method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results>
   <method>alphaBetaStatistics</method>
   <trans_ID>999</trans_ID>
   <fund_ID>9999-1</fund_ID>
   <asOfDate>20140601</asOfDate>
   <oneDayAlpha>-0.000382166226067</oneDayAlpha>
   <oneDayAlphaAAR>-0.139490672514</oneDayAlphaAAR>
   <oneDayBeta>0.784239389332</oneDayBeta>
   <oneDayRSquared>0.474236337903</oneDayRSquared>
   <thirtyDayAlphaSkip>-0.0135795140984</thirtyDayAlphaSkip>
   <thirtyDayAlphaSkipAAR>-0.165217421531</thirtyDayAlphaSkipAAR>
   <thirtyDayBetaSkip>1.10705791049</thirtyDayBetaSkip>
   <thirtyDayRSquaredSkip>0.212718876197</thirtyDayRSquaredSkip>
   <thirtyDayAlphaSkipAvg>-0.012539911553</thirtyDayAlphaSkipAvg>
   <thirtyDayAlphaSkipStdDev>0.0146984654352</thirtyDayAlphaSkipStdDev>
   <thirtyDayAlphaSkipSkew>-1.04845164768</thirtyDayAlphaSkipSkew>
   <thirtyDayAlphaSkipKurt>-2.27163993635e-07</thirtyDayAlphaSkipKurt>
   <thirtyDayAlphaSliding>-0.0122942887032</thirtyDayAlphaSliding>
   <thirtyDayAlphaSlidingAAR>-0.149580512556</thirtyDayAlphaSlidingAAR>
   <thirtyDayBetaSliding>0.74361224135</thirtyDayBetaSliding>
   <thirtyDayRSquaredSliding>0.417777940906</thirtyDayRSquaredSliding>
   <thirtyDayAlphaSlidingAvg>-0.0127678922486</thirtyDayAlphaSlidingAvg>
   <thirtyDayAlphaSlidingStdDev>0.0195557621003</thirtyDayAlphaSlidingStdDev>
   <thirtyDayAlphaSlidingSkew>-0.440300311141</thirtyDayAlphaSlidingSkew>
   <thirtyDayAlphaSlidingKurt>-2.4192674435e-05</thirtyDayAlphaSlidingKurt>
   <thirtyDayReturnsAvg>-0.00282925551063</thirtyDayReturnsAvg>
   <thirtyDayReturnsStdDev>0.0166070142295</thirtyDayReturnsStdDev>
   <thirtyDayReturnsKurt>-2.9908504369e-07</thirtyDayReturnsKurt>
   <thirtyDayReturnsSkew>-0.719678537816</thirtyDayReturnsSkew>
   <thirtyDaySP500ReturnsAvg>0.00971065604241</thirtyDaySP500ReturnsAvg>
   <thirtyDaySP500ReturnsStdDev>0.0069186957551</thirtyDaySP500ReturnsStdDev>
   <thirtyDaySP500ReturnsKurt>-8.75323141021e-09</thirtyDaySP500ReturnsKurt>
   <thirtyDaySP500ReturnsSkew>-0.5364830053</thirtyDaySP500ReturnsSkew>
   <bestDailyAlpha>0.0186060584677</bestDailyAlpha>
   <bestWeeklyAlpha>0.0177732581498</bestWeeklyAlpha>
   <bestMonthlyAlpha>0.0263826635677</bestMonthlyAlpha>
   <best90Alpha>-0.00834075855082</best90Alpha>
   <best180Alpha>-0.0691092435933</best180Alpha>
   <best365Alpha />
   <worstDailyAlpha>-0.0167111360968</worstDailyAlpha>
   <worstWeeklyAlpha>-0.0403656760082</worstWeeklyAlpha>
   <worstMonthlyAlpha>-0.0631120829689</worstMonthlyAlpha>
   <worst90Alpha>-0.0883777891564</worst90Alpha>
   <worst180Alpha>-0.0771162318408</worst180Alpha>
   <worst365Alpha />
   <bestDailyAlphaAAR>6.79121134069</bestDailyAlphaAAR>
   <bestWeeklyAlphaAAR>0.926748460667</bestWeeklyAlphaAAR>
   <bestMonthlyAlphaAAR>0.320989073407</bestMonthlyAlphaAAR>
   <best90AlphaAAR>-0.0338264096783</best90AlphaAAR>
   <best180AlphaAAR>-0.140138188398</best180AlphaAAR>
   <worstDailyAlphaAAR>-6.09956467533</worstDailyAlphaAAR>
   <worstWeeklyAlphaAAR>-2.10478167757</worstWeeklyAlphaAAR>
   <worstMonthlyAlphaAAR>-0.767863676121</worstMonthlyAlphaAAR>
   <worst90AlphaAAR>-0.358421033801</worst90AlphaAAR>
   <worst180AlphaAAR>-0.156374581233</worst180AlphaAAR>
   <avgDailyAlpha>-0.000513685723123</avgDailyAlpha>
   <avgWeeklyAlpha>-0.00262838699678</avgWeeklyAlpha>
   <avgMonthlyAlpha>-0.0151711569592</avgMonthlyAlpha>
   <avg90Alpha>-0.0433718771782</avg90Alpha>
   <avg180Alpha>-0.0722704708124</avg180Alpha>
   <alphaSinceInception>-0.0736275978935</alphaSinceInception>
   <alphaSinceInceptionAAR>-0.145880897496</alphaSinceInceptionAAR>
   <alphaLastWeek>-0.000467849708333</alphaLastWeek>
   <alphaLastMonth>0.00351168469982</alphaLastMonth>
   <alphaLast90>-0.0550366670135</alphaLast90>
   <alphaLast180>-0.0722033420464</alphaLast180>
   <alphaLast270 />
   <alphaLast365 />
   <alphaLast730 />
   <alphaLast1095 />
   <alphaLast1825 />
   <returnSinceInception>0.00368145879667</returnSinceInception>
   <returnSinceInceptionAAR>0.00704704457044</returnSinceInceptionAAR>
   <returnLastWeek>0.0119565884138</returnLastWeek>
   <returnLastMonth>0.0284497665172</returnLastMonth>
   <returnLast90>-0.00765565372673</returnLast90>
   <returnLast180>0.00395648145832</returnLast180>
   <returnLast270 />
   <returnLast365 />
   <returnLast730 />
   <returnLast1095 />
   <returnLast1825 />
   <sp500SinceInception>0.0773090566902</sp500SinceInception>
   <sp500SinceInceptionAAR>0.152927942067</sp500SinceInceptionAAR>
   <sp500LastWeek>0.0124244381222</sp500LastWeek>
   <sp500LastMonth>0.0249380818174</sp500LastMonth>
   <sp500Last90>0.0473810132868</sp500Last90>
   <sp500Last180>0.0761598235047</sp500Last180>
   <sp500Last270 />
   <sp500Last365 />
   <sp500Last730 />
   <sp500Last1095 />
   <sp500Last1825 />
   <dailyAlphaAvg>-0.000513685723123</dailyAlphaAvg>
   <dailyAlphaStdDev>0.00580913212023</dailyAlphaStdDev>
   <dailyAlphaSkew>0.230095809639</dailyAlphaSkew>
   <dailyAlphaKurt>-1.79373432675e-07</dailyAlphaKurt>
   <oneDayReturnAvg>9.58764052415e-05</oneDayReturnAvg>
   <oneDayReturnStdDev>0.00775130402319</oneDayReturnStdDev>
   <oneDayReturnSkew>-0.375664174296</oneDayReturnSkew>
   <oneDayReturnKurt>-7.2516250117e-07</oneDayReturnKurt>
   <oneDaySP500ReturnAvg>0.000609562128364</oneDaySP500ReturnAvg>
   <oneDaySP500ReturnStdDev>0.00680649506401</oneDaySP500ReturnStdDev>
   <oneDaySP500ReturnSkew>-0.680127164604</oneDaySP500ReturnSkew>
   <oneDaySP500ReturnKurt>-3.90732113342e-07</oneDaySP500ReturnKurt>
   <oneEightyDayReturnsAvg>-0.00324565104447</oneEightyDayReturnsAvg>
   <oneEightyDayReturnsStdDev>0.00729076926474</oneEightyDayReturnsStdDev>
   <oneEightyDayReturnsSkew>-0.389135509555</oneEightyDayReturnsSkew>
   <oneEightyDayReturnsKurt>-1.9049513269e-08</oneEightyDayReturnsKurt>
   <oneEightyDaySP500ReturnsAvg>0.0690248197679</oneEightyDaySP500ReturnsAvg>
   <oneEightyDaySP500ReturnsStdDev>0.00649032607408</oneEightyDaySP500ReturnsStdDev>
   <oneEightyDaySP500ReturnsSkew>0.178285839812</oneEightyDaySP500ReturnsSkew>
   <oneEightyDaySP500ReturnsKurt>-9.23595251162e-09</oneEightyDaySP500ReturnsKurt>
   <recentDailyReturnAvg>0.000101989148986</recentDailyReturnAvg>
   <recentDailyReturnStdDev>0.00794267286661</recentDailyReturnStdDev>
   <recentDailyReturnSkew>-0.368762923552</recentDailyReturnSkew>
   <recentDailyReturnKurt>-7.25131077043e-07</recentDailyReturnKurt>
   <recentDailySP500ReturnAvg>0.000630866626535</recentDailySP500ReturnAvg>
   <recentDailySP500ReturnStdDev>0.00695940682956</recentDailySP500ReturnStdDev>
   <recentDailySP500ReturnSkew>-0.676320910342</recentDailySP500ReturnSkew>
   <recentDailySP500ReturnKurt>-3.91397465472e-07</recentDailySP500ReturnKurt>
   <oneDaySSD>0.00574037219811</oneDaySSD>
   <oneDaySPSSD>0.00490885169387</oneDaySPSSD>
   <recentDailySSD>0.00588212793902</recentDailySSD>
   <recentDailySPSSD>0.00502239006263</recentDailySPSSD>
   <batAvgDaily>0.48031496063</batAvgDaily>
   <batAvgWeekly>0.429347826087</batAvgWeekly>
   <batAvgMonthly>0.223602484472</batAvgMonthly>
   <batAvg90>0.0</batAvg90>
   <batAvg180>0.0</batAvg180>
   <batAvg365 />
   <monthBat90>0.183333333333</monthBat90>
   <monthBat180>0.233333333333</monthBat180>
   <monthBatYear />
   <quarterBat180>0.0</quarterBat180>
   <quarterBatYear />
   <worstMonthAlphaLast90 />
   <worstMonthAlphaLast180>-0.0631120829689</worstMonthAlphaLast180>
   <worstMonthAlphaLastYear />
   <worstQuarterAlphaLast180>-0.0883777891564</worstQuarterAlphaLast180>
   <worstQuarterAlphaLastYear />
</results>
*/

								// Set main level vars
								$transID						= $xml->trans_ID;
								$fundID							= $xml->fund_ID;
								$asOfDate						= $xml->asOfDate;
								$unixDate					   	= mktime(5,0,0,substr($asOfDate,4,2),substr($asOfDate,6,2),substr($asOfDate,0,4));
								$oneDayAlpha 					= $xml->oneDayAlpha;
								$oneDayAlphaAAR 				= $xml->oneDayAlphaAAR;
								$oneDayBeta 					= $xml->oneDayBeta;
								$oneDayRSquared 				= $xml->oneDayRSquared;
								$thirtyDayAlphaSkip 			= $xml->thirtyDayAlphaSkip;
								$thirtyDayAlphaSkipAAR 			= $xml->thirtyDayAlphaSkipAAR;
								$thirtyDayBetaSkip 				= $xml->thirtyDayBetaSkip;
								$thirtyDayRSquaredSkip 			= $xml->thirtyDayRSquaredSkip;
								$thirtyDayAlphaSkipAvg 			= $xml->thirtyDayAlphaSkipAvg;
								$thirtyDayAlphaSkipStdDev 		= $xml->thirtyDayAlphaSkipStdDev;
								$thirtyDayAlphaSkipSkew 		= $xml->thirtyDayAlphaSkipSkew;
								$thirtyDayAlphaSkipKurt 		= $xml->thirtyDayAlphaSkipKurt;
								$thirtyDayAlphaSliding			= $xml->thirtyDayAlphaSliding;
								$thirtyDayAlphaSlidingAAR 		= $xml->thirtyDayAlphaSlidingAAR;
								$thirtyDayBetaSliding 			= $xml->thirtyDayBetaSliding;
								$thirtyDayRSquaredSliding 		= $xml->thirtyDayRSquaredSliding;
								$thirtyDayAlphaSlidingAvg 		= $xml->thirtyDayAlphaSlidingAvg;
								$thirtyDayAlphaSlidingStdDev 	= $xml->thirtyDayAlphaSlidingStdDev;
								$thirtyDayAlphaSlidingSkew 		= $xml->thirtyDayAlphaSlidingSkew;
								$thirtyDayAlphaSlidingKurt 		= $xml->thirtyDayAlphaSlidingKurt;
								$thirtyDayReturnsAvg 			= $xml->thirtyDayReturnsAvg;
								$thirtyDayReturnsStdDev 		= $xml->thirtyDayReturnsStdDev;
								$thirtyDayReturnsKurt 			= $xml->thirtyDayReturnsKurt;
								$thirtyDayReturnsSkew 			= $xml->thirtyDayReturnsSkew;
								$thirtyDaySP500ReturnsAvg 		= $xml->thirtyDaySP500ReturnsAvg;
								$thirtyDaySP500ReturnsStdDev 	= $xml->thirtyDaySP500ReturnsStdDev;
								$thirtyDaySP500ReturnsKurt 		= $xml->thirtyDaySP500ReturnsKurt;
								$thirtyDaySP500ReturnsSkew 		= $xml->thirtyDaySP500ReturnsSkew;
								$bestDailyAlpha 				= $xml->bestDailyAlpha;
								$bestWeeklyAlpha 				= $xml->bestWeeklyAlpha;
								$bestMonthlyAlpha 				= $xml->bestMonthlyAlpha;
								$best90Alpha 					= $xml->best90Alpha;
								$best180Alpha 					= $xml->best180Alpha;
								$best365Alpha 					= $xml->best365Alpha;
								$worstDailyAlpha 				= $xml->worstDailyAlpha;
								$worstWeeklyAlpha 				= $xml->worstWeeklyAlpha;
								$worstMonthlyAlpha 				= $xml->worstMonthlyAlpha;
								$worst90Alpha 					= $xml->worst90Alpha;
								$worst180Alpha 					= $xml->worst180Alpha;
								$worst365Alpha 					= $xml->worst365Alpha;
								$bestDailyAlphaAAR 				= $xml->bestDailyAlphaAAR;
								$bestWeeklyAlphaAAR 			= $xml->bestWeeklyAlphaAAR;
								$bestMonthlyAlphaAAR 			= $xml->bestMonthlyAlphaAAR;
								$best90AlphaAAR 				= $xml->best90AlphaAAR;
								$best180AlphaAAR 				= $xml->best180AlphaAAR;
								$worstDailyAlphaAAR 			= $xml->worstDailyAlphaAAR;
								$worstWeeklyAlphaAAR 			= $xml->worstWeeklyAlphaAAR;
								$worstMonthlyAlphaAAR 			= $xml->worstMonthlyAlphaAAR;
								$worst90AlphaAAR 				= $xml->worst90AlphaAAR;
								$worst180AlphaAAR 				= $xml->worst180AlphaAAR;
								$avgDailyAlpha 					= $xml->avgDailyAlpha;
								$avgWeeklyAlpha 				= $xml->avgWeeklyAlpha;
								$avgMonthlyAlpha 				= $xml->avgMonthlyAlpha;
								$avg90Alpha 					= $xml->avg90Alpha;
								$avg180Alpha 					= $xml->avg180Alpha;
								$alphaSinceInception 			= $xml->alphaSinceInception;
								$alphaSinceInceptionAAR 		= $xml->alphaSinceInceptionAAR;
								$alphaLastWeek 					= $xml->alphaLastWeek;
								$alphaLastMonth 				= $xml->alphaLastMonth;
								$alphaLast90 					= $xml->alphaLast90;
								$alphaLast180 					= $xml->alphaLast180;
								$alphaLast270 					= $xml->alphaLast270;
								$alphaLast365 					= $xml->alphaLast365;
								$alphaLast730 					= $xml->alphaLast730;
								$alphaLast1095 					= $xml->alphaLast1095;
								$alphaLast1825 					= $xml->alphaLast1825;
								$returnSinceInception 			= $xml->returnSinceInception;
								$returnSinceInceptionAAR 		= $xml->returnSinceInceptionAAR;
								$returnLastWeek 				= $xml->returnLastWeek;
								$returnLastMonth 				= $xml->returnLastMonth;
								$returnLast90 					= $xml->returnLast90;
								$returnLast180 					= $xml->returnLast180;
								$returnLast270 					= $xml->returnLast270;
								$returnLast365 					= $xml->returnLast365;
								$returnLast730 					= $xml->returnLast730;
								$returnLast1095 				= $xml->returnLast1095;
								$returnLast1825 				= $xml->returnLast1825;
								$sp500SinceInception 			= $xml->sp500SinceInception;
								$sp500SinceInceptionAAR 		= $xml->sp500SinceInceptionAAR;
								$sp500LastWeek 					= $xml->sp500LastWeek;
								$sp500LastMonth 				= $xml->sp500LastMonth;
								$sp500Last90 					= $xml->sp500Last90;
								$sp500Last180 					= $xml->sp500Last180;
								$sp500Last270 					= $xml->sp500Last270;
								$sp500Last365 					= $xml->sp500Last365;
								$sp500Last730 					= $xml->sp500Last730;
								$sp500Last1095 					= $xml->sp500Last1095;
								$sp500Last1825 					= $xml->sp500Last1825;
								$dailyAlphaAvg 					= $xml->dailyAlphaAvg;
								$dailyAlphaStdDev 				= $xml->dailyAlphaStdDev;
								$dailyAlphaSkew 				= $xml->dailyAlphaSkew;
								$dailyAlphaKurt 				= $xml->dailyAlphaKurt;
								$oneDayReturnAvg 				= $xml->oneDayReturnAvg;
								$oneDayReturnStdDev 			= $xml->oneDayReturnStdDev;
								$oneDayReturnSkew 				= $xml->oneDayReturnSkew;
								$oneDayReturnKurt 				= $xml->oneDayReturnKurt;
								$oneDaySP500ReturnAvg 			= $xml->oneDaySP500ReturnAvg;
								$oneDaySP500ReturnStdDev 		= $xml->oneDaySP500ReturnStdDev;
								$oneDaySP500ReturnSkew 			= $xml->oneDaySP500ReturnSkew;
								$oneDaySP500ReturnKurt 			= $xml->oneDaySP500ReturnKurt;
								$oneEightyDayReturnsAvg 		= $xml->oneEightyDayReturnsAvg;
								$oneEightyDayReturnsStdDev 		= $xml->oneEightyDayReturnsStdDev;
								$oneEightyDayReturnsSkew 		= $xml->oneEightyDayReturnsSkew;
								$oneEightyDayReturnsKurt 		= $xml->oneEightyDayReturnsKurt;
								$oneEightyDaySP500ReturnsAvg 	= $xml->oneEightyDaySP500ReturnsAvg;
								$oneEightyDaySP500ReturnsStdDev	= $xml->oneEightyDaySP500ReturnsStdDev;
								$oneEightyDaySP500ReturnsSkew 	= $xml->oneEightyDaySP500ReturnsSkew;
								$oneEightyDaySP500ReturnsKurt 	= $xml->oneEightyDaySP500ReturnsKurt;
								$recentDailyReturnAvg 			= $xml->recentDailyReturnAvg;
								$recentDailyReturnStdDev 		= $xml->recentDailyReturnStdDev;
								$recentDailyReturnSkew 			= $xml->recentDailyReturnSkew;
								$recentDailyReturnKurt 			= $xml->recentDailyReturnKurt;
								$recentDailySP500ReturnAvg 		= $xml->recentDailySP500ReturnAvg;
								$recentDailySP500ReturnStdDev 	= $xml->recentDailySP500ReturnStdDev;
								$recentDailySP500ReturnSkew 	= $xml->recentDailySP500ReturnSkew;
								$recentDailySP500ReturnKurt 	= $xml->recentDailySP500ReturnKurt;
								$oneDaySSD 						= $xml->oneDaySSD;
								$oneDaySPSSD 					= $xml->oneDaySPSSD;
								$recentDailySSD 				= $xml->recentDailySSD;
								$recentDailySPSSD 				= $xml->recentDailySPSSD;
								$batAvgDaily 					= $xml->batAvgDaily;
								$batAvgWeekly 					= $xml->batAvgWeekly;
								$batAvgMonthly 					= $xml->batAvgMonthly;
								$batAvg90 						= $xml->batAvg90;
								$batAvg180 						= $xml->batAvg180;
								$batAvg365 						= $xml->batAvg365;
								$monthBat90 					= $xml->monthBat90;
								$monthBat180 					= $xml->monthBat180;
								$monthBatYear 					= $xml->monthBatYear;
								$quarterBat180 					= $xml->quarterBat180;
								$quarterBatYear 				= $xml->quarterBatYear;
								$worstMonthAlphaLast90 			= $xml->worstMonthAlphaLast90;
								$worstMonthAlphaLast180 		= $xml->worstMonthAlphaLast180;
								$worstMonthAlphaLastYear 		= $xml->worstMonthAlphaLastYear;
								$worstQuarterAlphaLast180 		= $xml->worstQuarterAlphaLast180;
								$worstQuarterAlphaLastYear 		= $xml->worstQuarterAlphaLastYear;
								$aContents						= explode("?>", $contents);

								// Let's see if this is for historical purposes
								if (substr(strtoupper($fundID), -1) == "H"){
									$fundID	 = substr($fundID, 0, -1);
									$table	 = $fund_alphabeta_history_table;
								}else{
									$table	 = $fund_alphabeta_table;
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
										oneDayAlpha,
										oneDayAlphaAAR,
										oneDayBeta,
										oneDayRSquared,
										thirtyDayAlphaSkip,
										thirtyDayAlphaSkipAAR,
										thirtyDayBetaSkip,
										thirtyDayRSquaredSkip,
										thirtyDayAlphaSkipAvg,
										thirtyDayAlphaSkipStdDev,
										thirtyDayAlphaSkipSkew,
										thirtyDayAlphaSkipKurt,
										thirtyDayAlphaSliding,
										thirtyDayAlphaSlidingAAR,
										thirtyDayBetaSliding,
										thirtyDayRSquaredSliding,
										thirtyDayAlphaSlidingAvg,
										thirtyDayAlphaSlidingStdDev,
										thirtyDayAlphaSlidingSkew,
										thirtyDayAlphaSlidingKurt,
										thirtyDayReturnsAvg,
										thirtyDayReturnsStdDev,
										thirtyDayReturnsKurt,
										thirtyDayReturnsSkew,
										thirtyDaySP500ReturnsAvg,
										thirtyDaySP500ReturnsStdDev,
										thirtyDaySP500ReturnsKurt,
										thirtyDaySP500ReturnsSkew,
										bestDailyAlpha,
										bestWeeklyAlpha,
										bestMonthlyAlpha,
										best90Alpha,
										best180Alpha,
										best365Alpha,
										worstDailyAlpha,
										worstWeeklyAlpha,
										worstMonthlyAlpha,
										worst90Alpha,
										worst180Alpha,
										worst365Alpha,
										bestDailyAlphaAAR,
										bestWeeklyAlphaAAR,
										bestMonthlyAlphaAAR,
										best90AlphaAAR,
										best180AlphaAAR,
										worstDailyAlphaAAR,
										worstWeeklyAlphaAAR,
										worstMonthlyAlphaAAR,
										worst90AlphaAAR,
										worst180AlphaAAR,
										avgDailyAlpha,
										avgWeeklyAlpha,
										avgMonthlyAlpha,
										avg90Alpha,
										avg180Alpha,
										alphaSinceInception,
										alphaSinceInceptionAAR,
										alphaLastWeek,
										alphaLastMonth,
										alphaLast90,
										alphaLast180,
										alphaLast270,
										alphaLast365,
										alphaLast730,
										alphaLast1095,
										alphaLast1825,
										returnSinceInception,
										returnSinceInceptionAAR,
										returnLastWeek,
										returnLastMonth,
										returnLast90,
										returnLast180,
										returnLast270,
										returnLast365,
										returnLast730,
										returnLast1095,
										returnLast1825,
										sp500SinceInception,
										sp500SinceInceptionAAR,
										sp500LastWeek,
										sp500LastMonth,
										sp500Last90,
										sp500Last180,
										sp500Last270,
										sp500Last365,
										sp500Last730,
										sp500Last1095,
										sp500Last1825,
										dailyAlphaAvg,
										dailyAlphaStdDev,
										dailyAlphaSkew,
										dailyAlphaKurt,
										oneDayReturnAvg,
										oneDayReturnStdDev,
										oneDayReturnSkew,
										oneDayReturnKurt,
										oneDaySP500ReturnAvg,
										oneDaySP500ReturnStdDev,
										oneDaySP500ReturnSkew,
										oneDaySP500ReturnKurt,
										oneEightyDayReturnsAvg,
										oneEightyDayReturnsStdDev,
										oneEightyDayReturnsSkew,
										oneEightyDayReturnsKurt,
										oneEightyDaySP500ReturnsAvg,
										oneEightyDaySP500ReturnsStdDev,
										oneEightyDaySP500ReturnsSkew,
										oneEightyDaySP500ReturnsKurt,
										recentDailyReturnAvg,
										recentDailyReturnStdDev,
										recentDailyReturnSkew,
										recentDailyReturnKurt,
										recentDailySP500ReturnAvg,
										recentDailySP500ReturnStdDev,
										recentDailySP500ReturnSkew,
										recentDailySP500ReturnKurt,
										oneDaySSD,
										oneDaySPSSD,
										recentDailySSD,
										recentDailySPSSD,
										batAvgDaily,
										batAvgWeekly,
										batAvgMonthly,
										batAvg90,
										batAvg180,
										batAvg365,
										monthBat90,
										monthBat180,
										monthBatYear,
										quarterBat180,
										quarterBatYear,
										worstMonthAlphaLast90,
										worstMonthAlphaLast180,
										worstMonthAlphaLastYear,
										worstQuarterAlphaLast180,
										worstQuarterAlphaLastYear
									)VALUES(
										:fundID,
										UNIX_TIMESTAMP(),
										:asOfDate,
										:unixDate,
										:oneDayAlpha,
										:oneDayAlphaAAR,
										:oneDayBeta,
										:oneDayRSquared,
										:thirtyDayAlphaSkip,
										:thirtyDayAlphaSkipAAR,
										:thirtyDayBetaSkip,
										:thirtyDayRSquaredSkip,
										:thirtyDayAlphaSkipAvg,
										:thirtyDayAlphaSkipStdDev,
										:thirtyDayAlphaSkipSkew,
										:thirtyDayAlphaSkipKurt,
										:thirtyDayAlphaSliding,
										:thirtyDayAlphaSlidingAAR,
										:thirtyDayBetaSliding,
										:thirtyDayRSquaredSliding,
										:thirtyDayAlphaSlidingAvg,
										:thirtyDayAlphaSlidingStdDev,
										:thirtyDayAlphaSlidingSkew,
										:thirtyDayAlphaSlidingKurt,
										:thirtyDayReturnsAvg,
										:thirtyDayReturnsStdDev,
										:thirtyDayReturnsKurt,
										:thirtyDayReturnsSkew,
										:thirtyDaySP500ReturnsAvg,
										:thirtyDaySP500ReturnsStdDev,
										:thirtyDaySP500ReturnsKurt,
										:thirtyDaySP500ReturnsSkew,
										:bestDailyAlpha,
										:bestWeeklyAlpha,
										:bestMonthlyAlpha,
										:best90Alpha,
										:best180Alpha,
										:best365Alpha,
										:worstDailyAlpha,
										:worstWeeklyAlpha,
										:worstMonthlyAlpha,
										:worst90Alpha,
										:worst180Alpha,
										:worst365Alpha,
										:bestDailyAlphaAAR,
										:bestWeeklyAlphaAAR,
										:bestMonthlyAlphaAAR,
										:best90AlphaAAR,
										:best180AlphaAAR,
										:worstDailyAlphaAAR,
										:worstWeeklyAlphaAAR,
										:worstMonthlyAlphaAAR,
										:worst90AlphaAAR,
										:worst180AlphaAAR,
										:avgDailyAlpha,
										:avgWeeklyAlpha,
										:avgMonthlyAlpha,
										:avg90Alpha,
										:avg180Alpha,
										:alphaSinceInception,
										:alphaSinceInceptionAAR,
										:alphaLastWeek,
										:alphaLastMonth,
										:alphaLast90,
										:alphaLast180,
										:alphaLast270,
										:alphaLast365,
										:alphaLast730,
										:alphaLast1095,
										:alphaLast1825,
										:returnSinceInception,
										:returnSinceInceptionAAR,
										:returnLastWeek,
										:returnLastMonth,
										:returnLast90,
										:returnLast180,
										:returnLast270,
										:returnLast365,
										:returnLast730,
										:returnLast1095,
										:returnLast1825,
										:sp500SinceInception,
										:sp500SinceInceptionAAR,
										:sp500LastWeek,
										:sp500LastMonth,
										:sp500Last90,
										:sp500Last180,
										:sp500Last270,
										:sp500Last365,
										:sp500Last730,
										:sp500Last1095,
										:sp500Last1825,
										:dailyAlphaAvg,
										:dailyAlphaStdDev,
										:dailyAlphaSkew,
										:dailyAlphaKurt,
										:oneDayReturnAvg,
										:oneDayReturnStdDev,
										:oneDayReturnSkew,
										:oneDayReturnKurt,
										:oneDaySP500ReturnAvg,
										:oneDaySP500ReturnStdDev,
										:oneDaySP500ReturnSkew,
										:oneDaySP500ReturnKurt,
										:oneEightyDayReturnsAvg,
										:oneEightyDayReturnsStdDev,
										:oneEightyDayReturnsSkew,
										:oneEightyDayReturnsKurt,
										:oneEightyDaySP500ReturnsAvg,
										:oneEightyDaySP500ReturnsStdDev,
										:oneEightyDaySP500ReturnsSkew,
										:oneEightyDaySP500ReturnsKurt,
										:recentDailyReturnAvg,
										:recentDailyReturnStdDev,
										:recentDailyReturnSkew,
										:recentDailyReturnKurt,
										:recentDailySP500ReturnAvg,
										:recentDailySP500ReturnStdDev,
										:recentDailySP500ReturnSkew,
										:recentDailySP500ReturnKurt,
										:oneDaySSD,
										:oneDaySPSSD,
										:recentDailySSD,
										:recentDailySPSSD,
										:batAvgDaily,
										:batAvgWeekly,
										:batAvgMonthly,
										:batAvg90,
										:batAvg180,
										:batAvg365,
										:monthBat90,
										:monthBat180,
										:monthBatYear,
										:quarterBat180,
										:quarterBatYear,
										:worstMonthAlphaLast90,
										:worstMonthAlphaLast180,
										:worstMonthAlphaLastYear,
										:worstQuarterAlphaLast180,
										:worstQuarterAlphaLastYear
									)
								";
								try{
									$rsInsert = $mLink->prepare($query);
									$aValues = array(
										':fundID'							=> $fundID,
										':asOfDate'							=> $asOfDate,
										':unixDate'							=> $unixDate,
										':oneDayAlpha'						=> $oneDayAlpha,
										':oneDayAlphaAAR'					=> $oneDayAlphaAAR,
										':oneDayBeta'						=> $oneDayBeta,
										':oneDayRSquared'					=> $oneDayRSquared,
										':thirtyDayAlphaSkip'				=> $thirtyDayAlphaSkip,
										':thirtyDayAlphaSkipAAR'			=> $thirtyDayAlphaSkipAAR,
										':thirtyDayBetaSkip'				=> $thirtyDayBetaSkip,
										':thirtyDayRSquaredSkip'			=> $thirtyDayRSquaredSkip,
										':thirtyDayAlphaSkipAvg'			=> $thirtyDayAlphaSkipAvg,
										':thirtyDayAlphaSkipStdDev'			=> $thirtyDayAlphaSkipStdDev,
										':thirtyDayAlphaSkipSkew'			=> $thirtyDayAlphaSkipSkew,
										':thirtyDayAlphaSkipKurt'			=> $thirtyDayAlphaSkipKurt,
										':thirtyDayAlphaSliding'			=> $thirtyDayAlphaSliding,
										':thirtyDayAlphaSlidingAAR'			=> $thirtyDayAlphaSlidingAAR,
										':thirtyDayBetaSliding'				=> $thirtyDayBetaSliding,
										':thirtyDayRSquaredSliding'			=> $thirtyDayRSquaredSliding,
										':thirtyDayAlphaSlidingAvg'			=> $thirtyDayAlphaSlidingAvg,
										':thirtyDayAlphaSlidingStdDev'		=> $thirtyDayAlphaSlidingStdDev,
										':thirtyDayAlphaSlidingSkew'		=> $thirtyDayAlphaSlidingSkew,
										':thirtyDayAlphaSlidingKurt'		=> $thirtyDayAlphaSlidingKurt,
										':thirtyDayReturnsAvg'				=> $thirtyDayReturnsAvg,
										':thirtyDayReturnsStdDev'			=> $thirtyDayReturnsStdDev,
										':thirtyDayReturnsKurt'				=> $thirtyDayReturnsKurt,
										':thirtyDayReturnsSkew'				=> $thirtyDayReturnsSkew,
										':thirtyDaySP500ReturnsAvg'			=> $thirtyDaySP500ReturnsAvg,
										':thirtyDaySP500ReturnsStdDev'		=> $thirtyDaySP500ReturnsStdDev,
										':thirtyDaySP500ReturnsKurt'		=> $thirtyDaySP500ReturnsKurt,
										':thirtyDaySP500ReturnsSkew'		=> $thirtyDaySP500ReturnsSkew,
										':bestDailyAlpha'					=> $bestDailyAlpha,
										':bestWeeklyAlpha'					=> $bestWeeklyAlpha,
										':bestMonthlyAlpha'					=> $bestMonthlyAlpha,
										':best90Alpha'						=> $best90Alpha,
										':best180Alpha'						=> $best180Alpha,
										':best365Alpha'						=> $best365Alpha,
										':worstDailyAlpha'					=> $worstDailyAlpha,
										':worstWeeklyAlpha'					=> $worstWeeklyAlpha,
										':worstMonthlyAlpha'				=> $worstMonthlyAlpha,
										':worst90Alpha'						=> $worst90Alpha,
										':worst180Alpha'					=> $worst180Alpha,
										':worst365Alpha'					=> $worst365Alpha,
										':bestDailyAlphaAAR'				=> $bestDailyAlphaAAR,
										':bestWeeklyAlphaAAR'				=> $bestWeeklyAlphaAAR,
										':bestMonthlyAlphaAAR'				=> $bestMonthlyAlphaAAR,
										':best90AlphaAAR'					=> $best90AlphaAAR,
										':best180AlphaAAR'					=> $best180AlphaAAR,
										':worstDailyAlphaAAR'				=> $worstDailyAlphaAAR,
										':worstWeeklyAlphaAAR'				=> $worstWeeklyAlphaAAR,
										':worstMonthlyAlphaAAR'				=> $worstMonthlyAlphaAAR,
										':worst90AlphaAAR'					=> $worst90AlphaAAR,
										':worst180AlphaAAR'					=> $worst180AlphaAAR,
										':avgDailyAlpha'					=> $avgDailyAlpha,
										':avgWeeklyAlpha'					=> $avgWeeklyAlpha,
										':avgMonthlyAlpha'					=> $avgMonthlyAlpha,
										':avg90Alpha'						=> $avg90Alpha,
										':avg180Alpha'						=> $avg180Alpha,
										':alphaSinceInception'				=> $alphaSinceInception,
										':alphaSinceInceptionAAR'			=> $alphaSinceInceptionAAR,
										':alphaLastWeek'					=> $alphaLastWeek,
										':alphaLastMonth'					=> $alphaLastMonth,
										':alphaLast90'						=> $alphaLast90,
										':alphaLast180'						=> $alphaLast180,
										':alphaLast270'						=> $alphaLast270,
										':alphaLast365'						=> $alphaLast365,
										':alphaLast730'						=> $alphaLast730,
										':alphaLast1095'					=> $alphaLast1095,
										':alphaLast1825'					=> $alphaLast1825,
										':returnSinceInception'				=> $returnSinceInception,
										':returnSinceInceptionAAR'			=> $returnSinceInceptionAAR,
										':returnLastWeek'					=> $returnLastWeek,
										':returnLastMonth'					=> $returnLastMonth,
										':returnLast90'						=> $returnLast90,
										':returnLast180'					=> $returnLast180,
										':returnLast270'					=> $returnLast270,
										':returnLast365'					=> $returnLast365,
										':returnLast730'					=> $returnLast730,
										':returnLast1095'					=> $returnLast1095,
										':returnLast1825'					=> $returnLast1825,
										':sp500SinceInception'				=> $sp500SinceInception,
										':sp500SinceInceptionAAR'			=> $sp500SinceInceptionAAR,
										':sp500LastWeek'					=> $sp500LastWeek,
										':sp500LastMonth'					=> $sp500LastMonth,
										':sp500Last90'						=> $sp500Last90,
										':sp500Last180'						=> $sp500Last180,
										':sp500Last270'						=> $sp500Last270,
										':sp500Last365'						=> $sp500Last365,
										':sp500Last730'						=> $sp500Last730,
										':sp500Last1095'					=> $sp500Last1095,
										':sp500Last1825'					=> $sp500Last1825,
										':dailyAlphaAvg'					=> $dailyAlphaAvg,
										':dailyAlphaStdDev'					=> $dailyAlphaStdDev,
										':dailyAlphaSkew'					=> $dailyAlphaSkew,
										':dailyAlphaKurt'					=> $dailyAlphaKurt,
										':oneDayReturnAvg'					=> $oneDayReturnAvg,
										':oneDayReturnStdDev'				=> $oneDayReturnStdDev,
										':oneDayReturnSkew'					=> $oneDayReturnSkew,
										':oneDayReturnKurt'					=> $oneDayReturnKurt,
										':oneDaySP500ReturnAvg'				=> $oneDaySP500ReturnAvg,
										':oneDaySP500ReturnStdDev'			=> $oneDaySP500ReturnStdDev,
										':oneDaySP500ReturnSkew'			=> $oneDaySP500ReturnSkew,
										':oneDaySP500ReturnKurt'			=> $oneDaySP500ReturnKurt,
										':oneEightyDayReturnsAvg'			=> $oneEightyDayReturnsAvg,
										':oneEightyDayReturnsStdDev'		=> $oneEightyDayReturnsStdDev,
										':oneEightyDayReturnsSkew'			=> $oneEightyDayReturnsSkew,
										':oneEightyDayReturnsKurt'			=> $oneEightyDayReturnsKurt,
										':oneEightyDaySP500ReturnsAvg'		=> $oneEightyDaySP500ReturnsAvg,
										':oneEightyDaySP500ReturnsStdDev'	=> $oneEightyDaySP500ReturnsStdDev,
										':oneEightyDaySP500ReturnsSkew'		=> $oneEightyDaySP500ReturnsSkew,
										':oneEightyDaySP500ReturnsKurt'		=> $oneEightyDaySP500ReturnsKurt,
										':recentDailyReturnAvg'				=> $recentDailyReturnAvg,
										':recentDailyReturnStdDev'			=> $recentDailyReturnStdDev,
										':recentDailyReturnSkew'			=> $recentDailyReturnSkew,
										':recentDailyReturnKurt'			=> $recentDailyReturnKurt,
										':recentDailySP500ReturnAvg'		=> $recentDailySP500ReturnAvg,
										':recentDailySP500ReturnStdDev'		=> $recentDailySP500ReturnStdDev,
										':recentDailySP500ReturnSkew'		=> $recentDailySP500ReturnSkew,
										':recentDailySP500ReturnKurt'		=> $recentDailySP500ReturnKurt,
										':oneDaySSD'						=> $oneDaySSD,
										':oneDaySPSSD'						=> $oneDaySPSSD,
										':recentDailySSD'					=> $recentDailySSD,
										':recentDailySPSSD'					=> $recentDailySPSSD,
										':batAvgDaily'						=> $batAvgDaily,
										':batAvgWeekly'						=> $batAvgWeekly,
										':batAvgMonthly'					=> $batAvgMonthly,
										':batAvg90'							=> $batAvg90,
										':batAvg180'						=> $batAvg180,
										':batAvg365'						=> $batAvg365,
										':monthBat90'						=> $monthBat90,
										':monthBat180'						=> $monthBat180,
										':monthBatYear'						=> $monthBatYear,
										':quarterBat180'					=> $quarterBat180,
										':quarterBatYear'					=> $quarterBatYear,
										':worstMonthAlphaLast90'			=> $worstMonthAlphaLast90,
										':worstMonthAlphaLast180'			=> $worstMonthAlphaLast180,
										':worstMonthAlphaLastYear'			=> $worstMonthAlphaLastYear,
										':worstQuarterAlphaLast180'			=> $worstQuarterAlphaLast180,
										':worstQuarterAlphaLastYear'		=> $worstQuarterAlphaLastYear
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

$method
$trans_ID
$fund_ID
$asOfDate
$oneDayAlpha
$oneDayAlphaAAR
$oneDayBeta
$oneDayRSquared
$thirtyDayAlphaSkip
$thirtyDayAlphaSkipAAR
$thirtyDayBetaSkip
$thirtyDayRSquaredSkip
$thirtyDayAlphaSkipAvg
$thirtyDayAlphaSkipStdDev
$thirtyDayAlphaSkipSkew
$thirtyDayAlphaSkipKurt
$thirtyDayAlphaSliding
$thirtyDayAlphaSlidingAAR
$thirtyDayBetaSliding
$thirtyDayRSquaredSliding
$thirtyDayAlphaSlidingAvg
$thirtyDayAlphaSlidingStdDev
$thirtyDayAlphaSlidingSkew
$thirtyDayAlphaSlidingKurt
$thirtyDayReturnsAvg
$thirtyDayReturnsStdDev
$thirtyDayReturnsKurt
$thirtyDayReturnsSkew
$thirtyDaySP500ReturnsAvg
$thirtyDaySP500ReturnsStdDev
$thirtyDaySP500ReturnsKurt
$thirtyDaySP500ReturnsSkew
$bestDailyAlpha
$bestWeeklyAlpha
$bestMonthlyAlpha
$best90Alpha
$best180Alpha
$best365Alpha
$worstDailyAlpha
$worstWeeklyAlpha
$worstMonthlyAlpha
$worst90Alpha
$worst180Alpha
$worst365Alpha
$bestDailyAlphaAAR
$bestWeeklyAlphaAAR
$bestMonthlyAlphaAAR
$best90AlphaAAR
$best180AlphaAAR
$worstDailyAlphaAAR
$worstWeeklyAlphaAAR
$worstMonthlyAlphaAAR
$worst90AlphaAAR
$worst180AlphaAAR
$avgDailyAlpha
$avgWeeklyAlpha
$avgMonthlyAlpha
$avg90Alpha
$avg180Alpha
$alphaSinceInception
$alphaSinceInceptionAAR
$alphaLastWeek
$alphaLastMonth
$alphaLast90
$alphaLast180
$alphaLast270
$alphaLast365
$alphaLast730
$alphaLast1095
$alphaLast1825
$returnSinceInception
$returnSinceInceptionAAR
$returnLastWeek
$returnLastMonth
$returnLast90
$returnLast180
$returnLast270
$returnLast365
$returnLast730
$returnLast1095
$returnLast1825
$sp500SinceInception
$sp500SinceInceptionAAR
$sp500LastWeek
$sp500LastMonth
$sp500Last90
$sp500Last180
$sp500Last270
$sp500Last365
$sp500Last730
$sp500Last1095
$sp500Last1825
$dailyAlphaAvg
$dailyAlphaStdDev
$dailyAlphaSkew
$dailyAlphaKurt
$oneDayReturnAvg
$oneDayReturnStdDev
$oneDayReturnSkew
$oneDayReturnKurt
$oneDaySP500ReturnAvg
$oneDaySP500ReturnStdDev
$oneDaySP500ReturnSkew
$oneDaySP500ReturnKurt
$oneEightyDayReturnsAvg
$oneEightyDayReturnsStdDev
$oneEightyDayReturnsSkew
$oneEightyDayReturnsKurt
$oneEightyDaySP500ReturnsAvg
$oneEightyDaySP500ReturnsStdDev
$oneEightyDaySP500ReturnsSkew
$oneEightyDaySP500ReturnsKurt
$recentDailyReturnAvg
$recentDailyReturnStdDev
$recentDailyReturnSkew
$recentDailyReturnKurt
$recentDailySP500ReturnAvg
$recentDailySP500ReturnStdDev
$recentDailySP500ReturnSkew
$recentDailySP500ReturnKurt
$oneDaySSD
$oneDaySPSSD
$recentDailySSD
$recentDailySPSSD
$batAvgDaily
$batAvgWeekly
$batAvgMonthly
$batAvg90
$batAvg180
$batAvg365
$monthBat90
$monthBat180
$monthBatYear
$quarterBat180
$quarterBatYear
$worstMonthAlphaLast90
$worstMonthAlphaLast180
$worstMonthAlphaLastYear
$worstQuarterAlphaLast180
$worstQuarterAlphaLastYear
*/

?>