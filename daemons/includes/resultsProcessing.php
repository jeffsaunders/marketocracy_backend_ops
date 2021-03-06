 <?php
/*
This include covers the actual results processing by switching on the query method.
The case values are inserted based on the process that is being, well...processed.
Called from daemons/includes/resultsLoop.php
*/

				// Process the results depending on the method specified
				switch ($method){

				// Manager Admin Methods
					//--------------------------------------------------
					case "newManager":
						require("methods/newManagerMethod.php");
						break;

					//--------------------------------------------------
					case "newFund":
						require("methods/newFundMethod.php");
						break;

					//--------------------------------------------------
					case "deactivateFund":
						require("methods/deactivateFundMethod.php");
						break;

					//--------------------------------------------------
					case "managerPassword":
						require("methods/managerPasswordMethod.php");
						break;

					//--------------------------------------------------
					case "updateSymbol":
						require("methods/updateSymbolMethod.php");
						break;

					//--------------------------------------------------
					case "updateName":
						require("methods/updateNameMethod.php");
						break;

					//--------------------------------------------------
					case "setLegacyOn":
						require("methods/setLegacyOnMethod.php");
						break;

					//--------------------------------------------------
					case "setLegacyOff":
						require("methods/setLegacyOffMethod.php");
						break;


				// Fund Price Methods
					//--------------------------------------------------
					case "maxDate":
						require("methods/maxDateMethod.php");
						break;

					//--------------------------------------------------
					case "priceManager":
						require("methods/priceManagerMethod.php");
						break;

					//--------------------------------------------------
					case "livePrice":
						require("methods/livePriceMethod.php");
						break;

					//--------------------------------------------------
					case "priceRun":
						require("methods/priceRunMethod.php");
						break;

					//--------------------------------------------------
					case "aggregateStatistics":
						require("methods/aggregateStatisticsMethod.php");
						break;

					//--------------------------------------------------
					case "alphaBetaStatistics":
						require("methods/alphaBetaStatisticsMethod.php");
						break;

					//--------------------------------------------------
					case "periodPrices":
						require("methods/periodPricesMethod.php");
						break;


				// Stock Price Methods
					//--------------------------------------------------
					case "positionInfo":
						break;

					//--------------------------------------------------
					case "allPositionInfo":
						break;

					//--------------------------------------------------
					case "stockInfo":
						break;

					//--------------------------------------------------
					case "stockActions":
						break;


				// Trade Methods
					//--------------------------------------------------
					case "positionDetail":
						require("methods/positionDetailMethod.php");
						break;

					//--------------------------------------------------
					case "tradesForPosition":
						require("methods/tradesForPositionMethod.php");
						break;

					//--------------------------------------------------
					case "tradesForFund":
						require("methods/tradesForFundMethod.php");
						break;

					//--------------------------------------------------
					case "untrade":
						require("methods/untradeMethod.php");
						break;


				// CA Methods
					//--------------------------------------------------
					case "cashDividendsOnDate":
						require("methods/cashDividendsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "stockDividendsOnDate":
						require("methods/stockDividendsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "splitsOnDate":
						require("methods/splitsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "spinoffsOnDate":
						require("methods/spinoffsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "symbolChangesOnDate":
						require("methods/symbolChangesOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "cusipChangesOnDate":
						require("methods/cusipChangesOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "acquisitionsOnDate":
						require("methods/acquisitionsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "bankruptciesOnDate":
						require("methods/bankruptciesOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "listingsOnDate":
						require("methods/listingsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "delistingsOnDate":
						require("methods/delistingsOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "nameChangesOnDate":
						require("methods/nameChangesOnDateMethod.php");
						break;

					//--------------------------------------------------
					case "listingChangesOnDate":
						require("methods/listingChangesOnDateMethod.php");
						break;


				}  // End switch

				// Log it
				include("logAPITransaction.php");

?>