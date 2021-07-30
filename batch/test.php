<?php
				$date = "2018-08-15";
				$symbol = "AAPL";
				$apikey = "YQOMJLP73ZCF42AC"; // AlphaVantage API Key
				$url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=".$symbol."&apikey=".$apikey;
				// Use cURL to grab the JSON data from AlphaVantage.
				$curl = curl_init($url);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				$json = curl_exec($curl);
//var_dump(json_decode($json, true));
				// Decode and parse the data
				$aData = json_decode($json, true);
				$closePrice = $aData["Time Series (Daily)"][$date]["4. close"];
				$unixDate = mktime(5,0,0,substr($date,5,2),substr($date,8,2),substr($date,0,4));

echo $closePrice."\n";
echo $unixDate."\n";

?>