<?php
/*
The purpose of this script is to get the ALL the daily closing prices of the sector benchmark funds (SPDRs) from AlphaVantage using JSON and write them into the stocks_benchmarks_history table.
*Note - this will not run within a web browser.
*/

// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define all the benchmark symbols
$aSymbols = ["XLE","XLB","XLI","XLY","XLP","XLV","XLF","XLU","XLK","XLRE","VOX"]; // SPDRs

// Define the run date
$dateString = date("Y-m-d"); // Used in API calls and resulting data

// Define our AlphaVantage API Key
//$apikey = "YQOMJLP73ZCF42AC"; // Free Key (5 calls per minute, 500 per day)
$apikey = "N61VMD5KOL6ZZAB"; // Premium Key (smallest level - 30 calls per minute, Unlimited per day)

// Run for each defined symbol
foreach ($aSymbols as $symbol){

	// Wait 5 seconds for pacing
	sleep(5);

	// Get the requested data from AlphaVantage (JSON)
	$url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=".$symbol."&outputsize=full&apikey=".$apikey;
	$json = file_get_contents($url);
	$aData = json_decode($json, true);

//	$ch = curl_init();
//	curl_setopt($ch, CURLOPT_URL,($url));
//	curl_setopt($ch, CURLOPT_HEADER, 0);
//	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//	curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
//	$json = curl_exec ($ch);
//	curl_close($ch);
//	$aData = json_decode($json, true);


	// If the returned data is just the message about performing too many queries, then wait a few seconds and try again
	// e.g. Array([Noten] => Thank you for using Alpha Vantage! Please visit https://www.alphavantage.co/premium/ if you would like to have a higher API call volume.)
	// AlphaVantage Free only provides for 5 queries per minute, upgrades available to 15 per for $19.99/mo., 60 per for $49.99/mo., etc. (https://www.alphavantage.co/premium)
	while ($aData["Note"] != ""){

		// Wait 15 seconds
		sleep(15);

		// Try again to get the requested data from AlphaVantage
//		$url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=".$symbol."&apikey=".$apikey;
		$json = file_get_contents($url);
		$aData = json_decode($json, true);

//		$ch = curl_init();
//		curl_setopt($ch, CURLOPT_URL,($url));
//		curl_setopt($ch, CURLOPT_HEADER, 0);
//		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
//		$json = curl_exec ($ch);
//		curl_close($ch);
//		$aData = json_decode($json, true);

//echo $url."\n";
//print_r($aData);//die();
	}

//echo $url."\n";
//print_r($aData);//die();

	/* JSON will look like:
	{
		"Meta Data": {
			"1. Information": "Daily Prices (open, high, low, close) and Volumes",
			"2. Symbol": "XLE",
			"3. Last Refreshed": "2018-10-03 16:00:00",
			"4. Output Size": "Full size",
			"5. Time Zone": "US/Eastern"
		},
		"Time Series (Daily)": {
			"2018-10-03": {
				"1. open": "77.2000",
				"2. high": "77.7200",
				"3. low": "76.9800",
				"4. close": "77.5400",
				"5. volume": "12374088"
			},
			... Can't specify a date/range so it'll just return a lot of unneeded rows we can ignore ...
			"2018-05-14": {
				"1. open": "76.9700",
				"2. high": "77.6100",
				"3. low": "76.9700",
				"4. close": "77.2600",
				"5. volume": "10226324"
			}
		}
	}*/


	$aDays = $aData["Time Series (Daily)"];
//print_r($aDays);die();

	foreach($aDays as $key=>$day){
		$date	= date("Ymd", strtotime($key));
		$open	= $day["1. open"];
		$high	= $day["2. high"];
		$low	= $day["3. low"];
		$close	= $day["4. close"];
		$volume	= $day["5. volume"];


//	$date	= date("Ymd", strtotime($dateString));
//	$open	= $aData["Time Series (Daily)"][$dateString]["1. open"];
//	$high	= $aData["Time Series (Daily)"][$dateString]["2. high"];
//	$low	= $aData["Time Series (Daily)"][$dateString]["3. low"];
//	$close	= $aData["Time Series (Daily)"][$dateString]["4. close"];
//	$volume	= $aData["Time Series (Daily)"][$dateString]["5. volume"];

//echo $date."\n";
//echo $open."\n";
//echo $high."\n";
//echo $low."\n";
//echo $close."\n";
//echo $volume."\n\n";





//die();
		// Insert the day's data
		$query = "
			INSERT INTO stocks_benchmarks_history (
				symbol,
				date,
				unix_date,
				open,
				high,
				low,
				close,
				volume,
				timestamp,
				source
			) VALUES (
				:symbol,
				:date,
				:unix_date,
				:open,
				:high,
				:low,
				:close,
				:volume,
				UNIX_TIMESTAMP(),
				:source
			)
			ON DUPLICATE KEY UPDATE
				symbol = :symbol,
				date = :date,
				unix_date = :unix_date,
				open = :open,
				high = :high,
				low = :low,
				close = :close,
				volume = :volume,
				timestamp = UNIX_TIMESTAMP(),
				source = :source
		";
		try {
			$rsInsert = $mLink->prepare($query);
			$aValues = array(
				':symbol'	=> $symbol,
				':date'		=> $date,
				':unix_date'=> mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4)),
				':open'		=> $open,
				':high'		=> $high,
				':low'		=> $low,
				':close'	=> $close,
				':volume'	=> $volume,
				':source'	=> "AlphaVantage"
			);
			//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery."\n";//die();
			$rsInsert->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
	}
//die();
}

echo "Done!\n";

?>