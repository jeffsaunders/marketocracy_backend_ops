<?php
// This commandline batch script grabs the current price of each of the defined indexes from Google utilizing import.io screen scraping scripts (http://import.io, username "jeff.saunders@marketocracy.com", password "n0thing!)
// *Note - this will not run within a web browser.

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Define the index URLs & column names
$aIndex = array();

// S&P500
$aIndex[0][0] = "https://api.import.io/store/connector/ad4b130f-f113-4a8f-bb75-340fb99aa30d/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DINX%26ei%3DQa7hVoGmHcWd2Aas8ovwBg&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[0][1] = "index_sp500";
$aIndex[0][2] = "S&amp;P";

// NASDAQ
$aIndex[1][0] = "https://api.import.io/store/connector/8a41a93a-eb2c-4100-8d60-cc867524f088/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DIXIC%26ei%3DzanhVpjCEYSA2AaLz7vwCA&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[1][1] = "index_nasdaq";
$aIndex[1][2] = "NASDAQ";

// Dow Jones
$aIndex[2][0] = "https://api.import.io/store/connector/c63c530f-d9e6-496d-9caa-b98ef04d0e2a/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DDJI%26ei%3DsanhVtnrGMW9jAHvtpP4BQ&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[2][1] = "index_djia";
$aIndex[2][2] = "DJIA";

// NYSE
$aIndex[3][0] = "https://api.import.io/store/connector/99637bd8-68c0-4fc1-932e-ace6c35d6177/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DNYA%26ei%3DTK7hVoH6AYiljAHHoIboDw&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[3][1] = "index_nyse";
$aIndex[3][2] = "NYSE";

// Russell 2000
$aIndex[4][0] = "https://api.import.io/store/connector/af617a6f-5409-4ffe-beca-dd46933f150c/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DRUT%26ei%3DFa_hVoGdIMW9jAHvtpP4BQ&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[4][1] = "index_rut";
$aIndex[4][2] = "RUT";

// Wilshire 5000
$aIndex[5][0] = "https://api.import.io/store/connector/49e31f11-1ba5-4c1d-86d5-4163b4dc2d0a/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DW5000%26ei%3Dxq_hVvHUHcG62Abd_6iQAw&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[5][1] = "index_w5000";
$aIndex[5][2] = "W5000";

// S&P500TR
$aIndex[6][0] = "https://api.import.io/store/connector/c9440820-865f-4b8e-8b15-8b247efb3e2a/_query?input=webpage/url:https%3A%2F%2Fwww.google.com%2Ffinance%3Fq%3DSP500TR%26ei%3DabDhVrHLNdOS2AaU9JbAAQ&&_apikey=cc4cd938daf04872aaa2eb4916a0eecbecad128d20e54b2843e8c5e01afd3dda8febb90d0b3c62a58f83bc31eb7acac6cde0a1fc385b7d89dba9b84113dc504c4e72df9052779f87ea8e67d66077dd36";
$aIndex[6][1] = "index_sp500TR";
$aIndex[6][2] = "S&amp;P";

// Let's get started (forever)
$indexCounter = 0;
while (1){ // Run forever
//for ($x = 0; $x < sizeof($aIndex); $x++){  // Uncomment to run once and quit

	// Run only if the market is open
	if (isMarketOpen(time(), $linkID, "indices")){  // "indices" tells it to run 2 hours longer
//	if (1==1){  // Uncomment to run anytime

		// Make Google Finance call (via import.io) with cURL
		$curl	= curl_init($aIndex[$indexCounter][0]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$json	= curl_exec($curl);
		// Convert JSON to PHP object
		$phpObj	= json_decode($json);

//print_r($phpObj);

		// Set variables from returned results
		foreach($phpObj->results as $index){
			$indexName		= $index->index;
			$indexPrice		= str_replace(',', '', $index->price); // Strip commas
			$indexChange	= substr(str_replace(',', '', $index->change), 1);
			$indexDirection	= substr( $index->change, 0, 1);
//			$indexDirection	= $index->direction;
		}

//echo $indexName."\n";
//echo $indexPrice."\n";
//echo $indexChange."\n";
//echo $indexDirection."\n";

		// If we got a good price...
		if ($indexPrice > 100){ // Just skip if it's under $100
		// Check to see if $indexChange is positive or negative (for styling)
			if ($indexChange == 0){
				$statusColor	= "#57b5e3";
				$statusBar 		= "info";
				$operator		= "";
				$direction		= "";
			}else if($indexDirection == '-'){
				$statusColor	= "#ed4e2a";
				$statusBar 		= "danger";
				$operator		= "-";
//				$operator		= "&nabla; ";
				$direction		= "&#9660;";
			}else{
				$statusColor 	= "#3cc051";
				$statusBar 		= "success";
				$operator		= "+";
//				$operator		= "&Delta; ";
				$direction		= "&#9650;";
			}

			// Calculate the percent change and round it off to the hundreths place, and pad trailing zeros if needed
//			$percentChange = number_format(round(($indexChange/$indexPrice)*100, 2), 2);
			$percentChange = number_format(($indexChange/$indexPrice)*100, 2);
			if ($indexChange == 0){
				$percentChange = "Unchanged";
			}else{
				$percentChange = $direction.$percentChange."%";
			}

//echo $percentChange."\n";

			// Round the Index change to the hundreths place and pad trailing zeros if needed
			$indexChange = number_format(round($indexChange, 2), 2, '.', ',');

			// Add operator to the front of the string
			$indexChange = "".$operator."".$indexChange."";

//echo $indexChange."\n";

			// Format the Index Price to the hundreths place and add commas
			$indexPrice = number_format($indexPrice, 2, '.', ',');

//echo $indexPrice."\n";

			// Set the timestamp
			if (isMarketOpen(time(), $linkID, "none")){
				$indexTime = date('M j g:i:s A T');
			}else{ // After hours, grab the last time we ran it as market closed
				$query = "
					SELECT ".$aIndex[$indexCounter][1]."
					FROM ".$system_feeds_table."
					LIMIT 1
				";
				try{
					$rsStatus = $mLink->prepare($query);
					$rsStatus->execute();
				}
				catch(PDOException $error){
					// Log any error
					file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				}
				$Status = $rsStatus->fetch(PDO::FETCH_ASSOC);

				// Blow it apart and get the last update date
				$indexArray = explode('|', $Status[$aIndex[$indexCounter][1]]);
				$indexTime = $indexArray[5];
			}

//echo $indexTime."\n";

			// String together all variables to store in DB as an array seperated by "|"
			$updateIndex = "".$indexPrice."|".$indexChange."|".$percentChange."|".$statusColor."|".$statusBar."|".$indexTime;

//echo $updateIndex."\n\n";

			// Update the database
			$query = "
				UPDATE ".$system_feeds_table."
				SET	".$aIndex[$indexCounter][1]." = :index,
					timestamp = UNIX_TIMESTAMP()
			";
			try {
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
					':index'	=> $updateIndex,
				);
				// Prepared query - for error logging and debugging
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery;die();
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
		}
	}

	// Move on to the next index, start over if at the end
	if ($indexCounter == sizeof($aIndex) - 1){
		$indexCounter = 0;
	}else{
		$indexCounter++;
	}

	// Only get ^SP500TR after hours (between closing and the time we stop scraping)
	if (isMarketOpen(time(), $linkID, "none")){
		if ($indexCounter == 6){
			$indexCounter = 0;
		}
	}

	// Wait 8 seconds then do the next one (roughly one full pass per minute)
//	sleep(8);
	sleep(75);  // WHOA!  Slow down - exceeding the import.io limits by 10X
}

?>
