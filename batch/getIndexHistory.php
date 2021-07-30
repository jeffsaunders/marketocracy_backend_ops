<?php
// This commandline batch script grabs index history from Yahoo via YQL and stuffs it into a database table
// If a start date and an end date is passed, in the format YYYYMMDD, it will use those values, otherwise it will use today's date
// Examples:
//	/usr/bin/php /var/www/html/batch/getIndexHistory.php  (no passed dates)
//	/usr/bin/php /var/www/html/batch/getIndexHistory.php 20140601 20140630  (passed start and end dates)
// *Note - this will not run within a web browser.

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Assign the start and end dates
// This could be beefed up a bit to make sure BOTH dates are passed, they are formatted properly, etc.
//if (isset($_REQUEST['startDate'])){  // running from commandline requires interrogating the $argv[] array instead
if (isset($argv[1])){
	$startDate = substr($argv[1], 0, 4)."-".substr($argv[1], 4, 2)."-".substr($argv[1], 6, 24);
}else{
//	$startDate = date('Y-m-d',strtotime('yesterday'));
	$startDate = date('Y-m-d');
}
if (isset($argv[2])){
	$endDate = substr($argv[2], 0, 4)."-".substr($argv[2], 4, 2)."-".substr($argv[2], 6, 24);
}else{
//	$endDate = date('Y-m-d',strtotime('yesterday'));
	$endDate = date('Y-m-d');
}

//$aIndicies = array("^IXIC","^GSPC","^INDU","^NYA"); //NASDAQ, S&P, DJIA, NYSE (last two don't work anymore)
//$aIndicies = array("^IXIC","^GSPC","^RUT"); //NASDAQ, S&P, Russell 2000
$aIndicies = array("^GSPC","^IXIC","^NYA","^RUT","^W5000","^SP500TR"); //S&P, NASDAQ, NYSE, Russell 2000, Wilshire 5000, S&P Total Return (^INDU (DOW) doesn't work anymore)

// Loop through all the indexes
for ($indexCnt = 0; $indexCnt < count($aIndicies); $indexCnt++){
	$index = $aIndicies[$indexCnt];
	$yql = "https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.historicaldata%20where%20symbol%20%3D%20%22".$index."%22%20and%20startDate%20%3D%20%22".$startDate."%22%20and%20endDate%20%3D%20%22".$endDate."%22&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys";
//echo $yql."<br><br>";

	// create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, $yql);

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// $output contains the output string
	$output = curl_exec($ch);
//echo $output."\r\n";

	// close curl resource to free up system resources
	curl_close($ch);

	// Parse the XML results for DB insertion
	$parser = xml_parser_create();
	xml_parse_into_struct($parser, $output, $aValues);
	xml_parser_free($parser);
//print_r($aValues);

	// Pull apart the parsed XML
	$day = 0;
	for ($element = 0; $element <= sizeof($aValues); $element++){
		if ($aValues[$element]['type'] == "open" && $aValues[$element]['level'] == 3){ // About to start data
			// Initialize arrays
			$aTag[$day] = array();
			$aVal[$day] = array();
		}
		if ($aValues[$element]['level'] == 4){ // There be data here...
//echo $aValues[$element]['tag'] . " => " . $aValues[$element]['value'] . "\r\n";
			// Push the tag name and value onto their arrays
			array_push($aTag[$day], $aValues[$element]['tag']);
			array_push($aVal[$day], $aValues[$element]['value']);
		}
		if ($aValues[$element]['type'] == "close" && $aValues[$element]['level'] == 3){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\r\n\n";
			// Increment the day counter
			$day++;
		}
	}
	// Now build the query to insert the data
	for ($row = 0; $row < sizeof($aTag); $row++){

		// First delete any existing row placed there by the closing prices history process
		$query = "	DELETE FROM ".$index_history_table."
					WHERE `index` = '".$index."'
					AND unix_date = ".mktime(0, 0, 0, substr($aVal[$row][0], 5, 2), substr($aVal[$row][0], 8, 2), substr($aVal[$row][0], 0, 4))."
		";
//echo $query;
		$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

		// Now insert the new row
		$query = "INSERT INTO ".$index_history_table." (`index`, timestamp, unix_date, ";
		// Tack on all the tag names
		for ($element = 0; $element < sizeof($aTag[$row]); $element++){
			$query .= $aTag[$row][$element].", ";
		}
		// Pop the trailing ", " off
		$query = substr($query, 0, -2);
		// Now add the values
		$query .= ") VALUES ('".$index."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($aVal[$row][0], 5, 2), substr($aVal[$row][0], 8, 2), substr($aVal[$row][0], 0, 4)).", ";
		// Tack on all the values
		for ($element = 0; $element < sizeof($aVal[$row]); $element++){
			if (substr($aTag[$row][$element], 0, 4) == "DATE" || strpos($aTag[$row][$element], "DATE")){ // Character fields, add quotes
				$query .= "'".$aVal[$row][$element]."', ";
			}else{
				if ($aVal[$row][$element] == ''){ // Blank decimal value, set it to NULL
					$query .= "NULL, ";
				}else{
					$query .= $aVal[$row][$element].", ";
				}
			}
		}
		// Pop the trailing ", " off
		$query = substr($query, 0, -2);
		$query .= ")";
//echo $query."\r\n";
		// Do it!
		$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	}
}
//echo "Index history from ".$startDate." to ".$endDate." retrieved.\r\n";
?>