<?php
/*
The purpose of this script is to get the ALL the daily closing prices of the tracked indices from AlphaVantage using JSON and write them into the stock_index_history table.
*Note - this will not run within a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Handy Function(s)
//-----
// Determine if day is a market holiday
// Pass time & DB link
// Returns false if not, "Y" if it is, "E" if it's an early closing day
function isMarketHoliday($timestamp, $mLink) {

	// See if it's a holiday
	$nRows = $mLink->query("SELECT count(*) FROM system_holidays WHERE date = '".date("Y-m-d", $timestamp)."'")->fetchColumn(); 

	if ($nRows < 1){
		return false;  // It's not, bail!
	}

	// It is a holiday, see when the market closes
        $query = "
                SELECT *
                FROM system_holidays
                WHERE date = :date
        ";
        try {
                $rsHoliday = $mLink->prepare($query);
                $aValues = array(
                        ':date'         => date('Y-m-d', $timestamp)
                );
                $preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
                //return $preparedQuery;
                $rsHoliday->execute($aValues);
        }
        catch(PDOException $error){
                // Log any error
                file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
        }

        $holiday = $rsHoliday->fetch(PDO::FETCH_ASSOC);
        return $holiday['closed']; // "Y" if it is a holiday, "E" if it closes early

}
//-----

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// First, let's make sure today's not a market holiday
if (isMarketHoliday(time(), $mLink) != false){
	die("Holiday"); // It's a holiday, don't bother getting prices
}

// OK then, let's get going...

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define all the index symbols
$aSymbols = ["%5EGSPC","%5EIXIC","%5EDJIA","%5ENYA","%5ERUT","%5ERUA","%5ESP500TR"];

// Define the run date
$dateString = date("Y-m-d"); // Used in API calls and resulting data
//$dateString = "2018-11-29"; // Used in API calls and resulting data
//$dateString = "2020-06-10"; // Used in API calls and resulting data

// Define our AlphaVantage API Key
//$apikey = "YQOMJLP73ZCF42AC"; // Free Key (5 calls per minute, 500 per day)
$apikey = "N61VMD5KOL6ZZAB"; // Premium Key (smallest level - 30 calls per minute, Unlimited per day)

// Run for each defined symbol
foreach ($aSymbols as $symbol){

	// Wait 5 seconds for pacing
	sleep(5);

	// Get the requested data from AlphaVantage (JSON)
	$url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=".$symbol."&apikey=".$apikey;
	$json = file_get_contents($url);
	$aData = json_decode($json, true);
//echo $url."\n";

//	$ch = curl_init();
//	curl_setopt($ch, CURLOPT_URL,($url));
//	curl_setopt($ch, CURLOPT_HEADER, 0);
//	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//	curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
//	$json = curl_exec ($ch);
//	curl_close($ch);
//	$aData = json_decode($json, true);


	// If the returned data is just the message about performing too many queries, then wait a few seconds and try again
	// e.g. Array([Note] => Thank you for using Alpha Vantage! Please visit https://www.alphavantage.co/premium/ if you would like to have a higher API call volume.)
	// AlphaVantage Free only provides for 5 queries per minute, upgrades available to 15 per for $19.99/mo., 60 per for $49.99/mo., etc. (https://www.alphavantage.co/premium)
	$retries = 0;
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

		$retries++;
		if ($retries > 10){
			break;
		}

//echo $url."\n";
//print_r($aData);//die();
	}

//echo $url."\n";
//print_r($aData);//die();

	/* JSON will look like:
	{
		"Meta Data": {
			"1. Information": "Daily Prices (open, high, low, close) and Volumes",
			"2. Symbol": "^SP500TR",
			"3. Last Refreshed": "2018-10-03 16:00:00",
			"4. Output Size": "Compact",
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

	$date   = date("Y-m-d", strtotime($dateString));
	$open   = $aData["Time Series (Daily)"][$dateString]["1. open"];
	$high   = $aData["Time Series (Daily)"][$dateString]["2. high"];
	$low    = $aData["Time Series (Daily)"][$dateString]["3. low"];
	$close  = $aData["Time Series (Daily)"][$dateString]["4. close"];
	$volume = $aData["Time Series (Daily)"][$dateString]["5. volume"];

//echo $date."\n";
//echo $open."\n";
//echo $high."\n";
//echo $low."\n";
//echo $close."\n";
//echo $volume."\n\n";

//die();

	// Insert the day's data
	$query = "
		INSERT INTO stock_index_history (
			`index`,
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
			`index` = :symbol,
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
			':symbol'	=> urldecode($symbol),
			':date'		=> $date,
			':unix_date'=> mktime(5,0,0,substr($date,5,2),substr($date,8,2),substr($date,0,4)),
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

echo "Done!\n";

?>
