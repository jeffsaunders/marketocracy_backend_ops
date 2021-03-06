<?php
/*
This include verifies the existence of a company (by symbol) in the stock_symbols table and inserts it if it's not.
Called during processing of all CA methods (CAProcessing API) from daemons/includes/resultsProcessing.php
*/
									// Get company name and ID
									$query = "
										SELECT company_id, company
										FROM ".$stock_symbols_table."
										WHERE symbol = :symbol
										ORDER BY timestamp ASC
										LIMIT 1
									";
									try{
										$rsCompany = $mLink->prepare($query);
										$aValues = array(
											':symbol'	=> $symbol
										);
										$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
										//echo $preparedQuery."\n";
										$rsCompany->execute($aValues);
									}
									catch(PDOException $error){
										// Log any error
										file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
									}

									if ($rsCompany->rowCount() > 0){

										$company		= $rsCompany->fetch(PDO::FETCH_ASSOC);
										$company_id		= $company['company_id'];
										$company_name	= $company['company'];

									}else{

										$updateCompany = true;

										// Get company name from the ticker feed
										$query = "
											SELECT Name
											FROM stock_feed
											WHERE Symbol = :symbol
										";
										try{
											$rsCompanyName = $fLink->prepare($query);
											$aValues = array(
												':symbol'	=> $symbol
											);
											$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
											//echo $preparedQuery."\n";
											$rsCompanyName->execute($aValues);
										}
										catch(PDOException $error){
											// Log any error
											file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
										}

										if ($rsCompanyName->rowCount() < 1){

											if (isset($newSymbol)){

												// Try again with the $newSymbol (defined in calling script)
												$query = "
													SELECT Name
													FROM stock_feed
													WHERE Symbol = :symbol
												";
												try{
													$rsNewSymbol = $fLink->prepare($query);
													$aValues = array(
														':symbol'	=> $newSymbol
													);
													$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
													//echo $preparedQuery."\n";
													$rsNewSymbol->execute($aValues);
												}
												catch(PDOException $error){
													// Log any error
													file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
												}

												if ($rsNewSymbol->rowCount() > 0){

													// newSymbol found - use that
													$companyName	= $rsNewSymbol->fetch(PDO::FETCH_ASSOC);
													$company_name	= $companyName['Name'];

												}

											}else{

												// Issue a query to the Xignite API for the stock's company info, by symbol, to get the company name.
												$querystring = '_Token=EF2662FA141B4DC086F6A72B2D15AD2C&IdentifierType=Symbol&Identifier='.$symbol;
												$opts		= array('http'=>
																array('method' =>'POST',
																	  'port' =>'443',
																	  'header' =>'Content-type: application/x-www-form-urlencoded',
															 		  'content' =>$querystring
																)
															);
												$context	= stream_context_create($opts);
												$file		= fopen('http://globalquotes.xignite.com/v3/xGlobalQuotes.json/GetGlobalExtendedQuote', 'rb', false, $context) or die ("Xignite API Not Responding");
												$results	= @stream_get_contents($file);
												$json		= json_decode($results, TRUE);

												// Pluck the company name out of the results
												$company_name = $json['Security']['Name'];

											}

										}else{

											// Grab the name
											$companyName	= $rsCompanyName->fetch(PDO::FETCH_ASSOC);
											$company_name	= $companyName['Name'];

										}

										if (!isset($company_name)){

											// Just hard-code it
											$company_name	= "Unknown";
											$company_id		= 0;
											$updateCompany	= false;

										}

										// If we got the company name, add it
										if ($updateCompany){

											// Add to stock_companies
											$query = "
												INSERT INTO ".$stock_companies_table." (
													company_id,
													company,
													timestamp
												)
												SELECT COALESCE((SELECT MAX(company_id) FROM ".$stock_companies_table."), 0) + 1,
												:company_name,
												UNIX_TIMESTAMP()
											";
											try{
												$rsInsert = $mLink->prepare($query);
												$aValues = array(
													':company_name'	=> $company_name
												);
												$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
												//echo $preparedQuery."\n";
												$rsInsert->execute($aValues);
											}
											catch(PDOException $error){
												// Log any error
												file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
											}

										// Read it back to get the new company_id
											$query = "
												SELECT company_id
												FROM ".$stock_companies_table."
												WHERE company = :company_name
										   	";

											try{
												$rsCompanyID = $mLink->prepare($query);
												$aValues = array(
													':company_name'	=> $company_name
												);
												$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
												//echo $preparedQuery."\n";
												$rsCompanyID->execute($aValues);
											}
											catch(PDOException $error){
												// Log any error
												file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
											}

											$id		   	= $rsCompanyID->fetch(PDO::FETCH_ASSOC);
											$company_id	= $id["company_id"];

											// Pull in the stockInfo for future
											$query = "stockInfo|".$symbol;

											// Set the port number for the API call
											$port = rand(52100, 52499);

											// Execute the query call to submit the request
											exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');
										}
									}

?>