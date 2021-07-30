<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on port 22222
Once connected to port 22222 simply send it a string of values to pass to an XML request via the Xserve web server.
The string must be comma delimited with each element providing the following:

- method (This tells the XML driven script which method to execute)
- type (Single "day" or "range", blank if not applicable)
- login (Member's Portfolio login name)
- Fund ID (the fund ID from the new system)
- symbol (Member's portfolio symbol)
- startDate (The date whose info you want, start date if it's a range, in YYYYMMDD format)
- endDate (The end date if for a range, leave blank if only 1 day)

// Examples
maxDate
priceManager,,jeffsaunders
priceRun,day,jeffsaunders,1-1,JMF,20140601
priceRun,range,jeffsaunders,1-1,JMF,20140530,20140601
aggregateStatistics,,jeffsaunders,1-1,JMF,20140530
alphaBetaStatistics,,jeffsaunders,1-1,JMF,20140530
positionDetail,,jeffsaunders,1-1,JMF,20140601
*/


// Set up listener
$handle = fopen('php://stdin','r');
$input = fgets($handle, 1024);
fclose($handle);

$aInput = explode(",", $input);

//print_r($aInput);

// Assign passed values
$method	= trim($aInput[0]);
$type	= trim($aInput[1]);
$login	= trim($aInput[2]);
$fundID = trim($aInput[3]);
$symbol	= trim($aInput[4]);
$start	= trim($aInput[5]);
$end	= trim($aInput[6]);

// Build XML query string
$xmlString = "<fundPrice><method>".$method."</method>";
switch ($method){
	case "maxDate":
		$xmlString .= "</fundPrice>";
		break;

	case "priceManager":
		$xmlString .= "<login>".$login."</login></fundPrice>";
		break;

	case "priceRun":
		$xmlString .= "<login>".$login."</login><symbol>".$symbol."</symbol><startDate>".$start."</startDate><endDate>".($type == "day" ? $start : $end)."</endDate></fundPrice>";
		break;

	case "aggregateStatistics":
		$xmlString .= "<login>".$login."</login><symbol>".$symbol."</symbol><date>".$start."</date></fundPrice>";
		break;

	case "alphaBetaStatistics":
		$xmlString .= "<login>".$login."</login><symbol>".$symbol."</symbol><date>".$start."</date></fundPrice>";
		break;

	case "positionDetail":
		// Calls a different API so override the existing $xmlString
		$xmlString = "<stockPrice><method>".$method."</method><login>".$login."</login><symbol>".$symbol."</symbol><date>".$start."</date></stockPrice>";
		break;
}

//echo $xmlString;

// Build request
$url = preg_replace('/\s+/', ' ', trim("http://192.168.111.165/cgi-bin/".($method == "positionDetail" ? "stock" : "fund")."price.cgi?xmlString=".$xmlString));
//echo $url."\n";

// create curl resource
$ch = curl_init();

// set url
curl_setopt($ch, CURLOPT_URL, $url);

//return the transfer as a string
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// $output contains the output string
$output = curl_exec($ch);

// close curl resource to free up system resources
curl_close($ch);

//echo $output."\n";

// Connect to MySQL
$dbHost = "192.168.111.211";
$dbUser = "frontbasefetcher";
//$dbPass = "KfabyZcbE3"; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbPass = ""; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbName = "marketocracy";
$linkID = mysql_connect($dbHost, $dbUser, $dbPass) or die("Could not connect to MySQL");
mysql_select_db($dbName, $linkID) or die("Could not select ".$dbName." DB in MySQL");

// Parse the XML results for DB insertion
$parser = xml_parser_create();
xml_parse_into_struct($parser, $output, $aValues);
xml_parser_free($parser);

// Process the response depending on the method specified
switch ($method){
	case "maxDate":
		$maxDate = $aValues[1]['value']; // returns YYYYMMDD
//echo $maxDate."\n";
		// Now do something with it...
		$query = "UPDATE members_fund_maxdate SET timestamp = UNIX_TIMESTAMP(), maxdate = '".$maxDate."' WHERE 1";
//echo $query."\n";
		$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		echo "Done\r\n";
		break;

	case "priceManager":
		$priceManager = $aValues[1]['value']; // returns "sent" if successful
//echo $priceManager."\n";
		// Now do something with it...
		break;

	case "priceRun":
		$day = 0;
		for ($element = 0; $element <= sizeof($aValues); $element++){
			if ($aValues[$element]['type'] == "open" && $aValues[$element]['level'] == 2){ // About to start data
//echo "about to start\n";
				$aTag[$day] = array();
				$aVal[$day] = array();
			}
			if ($aValues[$element]['level'] == 3){
//echo $aValues[$element]['tag'] . " => " . $aValues[$element]['value'] . "\n";
				array_push($aTag[$day], $aValues[$element]['tag']);
				$value = $aValues[$element]['value'];
				if (strpos($value, "e")){ // Exponential number
					$value = number_format($aValues[$element]['value'], 0, '', ''); // Convert to a real number
				}
				array_push($aVal[$day], $value);
			}
			if ($aValues[$element]['type'] == "close" && $aValues[$element]['level'] == 2){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\n\n";
				$day++;
//echo "hit the end\n\n";
			}
		}

		for ($row = 0; $row < sizeof($aTag); $row++){
//			$query = "INSERT INTO ".$_SESSION['fund_pricing_table']." (";
			$query = "INSERT INTO members_fund_pricing (fund_id, timestamp, unix_date, ";
			for ($element = 0; $element < sizeof($aTag[$row]); $element++){
//echo $aTag[$row][$element]."\n";
				$query .= $aTag[$row][$element].", ";
			}
			$query = substr($query, 0, -2);
			$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($aVal[$row][0], 4, 2), substr($aVal[$row][0], 6, 2), substr($aVal[$row][0], 0, 4)).", ";
//			$query .= ") VALUES (UNIX_TIMESTAMP(), ".$aVal[$row][0].", ";
			for ($element = 0; $element < sizeof($aVal[$row]); $element++){
//echo $aVal[$row][$element]."\n";
				$query .= $aVal[$row][$element].", ";
			}
			$query = substr($query, 0, -2);
			$query .= ")";
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//echo $query."\r";
//break;
		}


		echo "Done\r\n";
//		flush();
//		sleep(1);
		break;

	case "aggregateStatistics":
		$day = 0;
		for ($element = 0; $element <= sizeof($aValues); $element++){
			if ($aValues[$element]['type'] == "open" && $aValues[$element]['level'] == 1){ // About to start data
//echo "about to start\n";
				$aTag[$day] = array();
				$aVal[$day] = array();
			}
			if ($aValues[$element]['level'] == 2){
//echo $aValues[$element]['tag'] . " => " . $aValues[$element]['value'] . "\n";
				array_push($aTag[$day], $aValues[$element]['tag']);
				$value = $aValues[$element]['value'];
				if (strpos($value, "e")){ // Exponential number
					$value = number_format($aValues[$element]['value'], 0, '', ''); // Convert to a real number
				}
				array_push($aVal[$day], $value);
			}
			if ($aValues[$element]['type'] == "close" && $aValues[$element]['level'] == 1){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\n\n";
				$day++;
//echo "hit the end\n\n";
			}
		}

		for ($row = 0; $row < sizeof($aTag); $row++){
//			$query = "INSERT INTO ".$_SESSION['fund_pricing_table']." (";
			$query = "INSERT INTO members_fund_aggregate (fund_id, timestamp, unix_date, ";
			for ($element = 0; $element < sizeof($aTag[$row]); $element++){
//echo $aTag[$row][$element]."\n";
				$query .= $aTag[$row][$element].", ";
			}
			$query = substr($query, 0, -2);
			$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($aVal[$row][0], 4, 2), substr($aVal[$row][0], 6, 2), substr($aVal[$row][0], 0, 4)).", ";
//			$query .= ") VALUES (UNIX_TIMESTAMP(), ".$aVal[$row][0].", ";
			for ($element = 0; $element < sizeof($aVal[$row]); $element++){
//echo $aVal[$row][$element]."\n";
				if (strpos($aTag[$row][$element], "DATE")){ // Date field, add quotes
					$query .= "'".$aVal[$row][$element]."', ";

				}else{
					if ($aVal[$row][$element] == ''){
						$query .= "NULL, ";
					}else{
						$query .= $aVal[$row][$element].", ";
					}
				}
			}
			$query = substr($query, 0, -2);
			$query .= ")";
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//echo $query."\r\n";
//break;
		}


		echo "Done\r\n";
//		flush();
//		sleep(1);
		break;

	case "alphaBetaStatistics":
		$day = 0;
		for ($element = 0; $element <= sizeof($aValues); $element++){
			if ($aValues[$element]['type'] == "open" && $aValues[$element]['level'] == 1){ // About to start data
//echo "about to start\n";
				$aTag[$day] = array();
				$aVal[$day] = array();
			}
			if ($aValues[$element]['level'] == 2){
//echo $aValues[$element]['tag'] . " => " . $aValues[$element]['value'] . "\n";
				array_push($aTag[$day], $aValues[$element]['tag']);
				$value = $aValues[$element]['value'];
				if (strpos($value, "e")){ // Exponential number
					$value = number_format($aValues[$element]['value'], 0, '', ''); // Convert to a real number
				}
				array_push($aVal[$day], $value);
			}
			if ($aValues[$element]['type'] == "close" && $aValues[$element]['level'] == 1){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\n\n";
				$day++;
//echo "hit the end\n\n";
			}
		}

		for ($row = 0; $row < sizeof($aTag); $row++){
//			$query = "INSERT INTO ".$_SESSION['fund_pricing_table']." (";
			$query = "INSERT INTO members_fund_alphabeta (fund_id, timestamp, unix_date, ";
			for ($element = 0; $element < sizeof($aTag[$row]); $element++){
//echo $aTag[$row][$element]."\n";
				$query .= $aTag[$row][$element].", ";
			}
			$query = substr($query, 0, -2);
			$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($aVal[$row][0], 4, 2), substr($aVal[$row][0], 6, 2), substr($aVal[$row][0], 0, 4)).", ";
//			$query .= ") VALUES (UNIX_TIMESTAMP(), ".$aVal[$row][0].", ";
			for ($element = 0; $element < sizeof($aVal[$row]); $element++){
//echo $aVal[$row][$element]."\n";
//////////////////////// clean this section up
				if (strpos($aTag[$row][$element], "DATE")){ // Date field, add quotes
					$query .= "'".$aVal[$row][$element]."', ";

				}else{
					if ($aVal[$row][$element] == ''){
						$query .= "NULL, ";
					}else{
						$query .= $aVal[$row][$element].", ";
					}
				}
			}
			$query = substr($query, 0, -2);
			$query .= ")";
//			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
echo $query."\r\n";
//break;
		}


		echo "Done\r\n";
//		flush();
//		sleep(1);
		break;

	case "positionDetail":
//echo $url."\r\n\n";
//echo $output."\r\n\n";
//print_r($aValues);
		$stock = 0;
		for ($element = 0; $element <= sizeof($aValues); $element++){
			if ($aValues[$element]['type'] == "open" && $aValues[$element]['level'] == 3){ // About to start data
//echo "about to start\n";
				$aTag[$stock] = array();
				$aVal[$stock] = array();
			}
			if ($aValues[$element]['level'] == 4){
//echo $aValues[$element]['tag'] . " => " . $aValues[$element]['value'] . "\n";
				array_push($aTag[$stock], $aValues[$element]['tag']);
				$value = $aValues[$element]['value'];
//				if (strpos($value, "e")){ // Exponential number
//					$value = number_format($aValues[$element]['value'], 0, '', ''); // Convert to a real number
//				}
				array_push($aVal[$stock], $value);
			}
			if ($aValues[$element]['type'] == "close" && $aValues[$element]['level'] == 3){ // Passed end of data
//print_r($aTag[$stock]);
//print_r($aVal[$stock]);
//echo "\n\n";
				$stock++;
//echo "hit the end\n\n";
			}
		}

		for ($row = 0; $row < sizeof($aTag); $row++){
//			$query = "INSERT INTO ".$_SESSION['fund_pricing_table']." (";
			$query = "INSERT INTO members_fund_details (fund_id, timestamp, unix_date, date, ";
			for ($element = 0; $element < sizeof($aTag[$row]); $element++){
//echo $aTag[$row][$element]."\n";
				$query .= $aTag[$row][$element].", ";
			}
			$query = substr($query, 0, -2);
			$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($start, 4, 2), substr($start, 6, 2), substr($start, 0, 4)).", ".$start.", ";
//			$query .= ") VALUES (UNIX_TIMESTAMP(), ".$aVal[$row][0].", ";
			for ($element = 0; $element < sizeof($aVal[$row]); $element++){
//echo $aVal[$row][$element]."\n";
//////////////////////// clean this section up
				if (strpos($aTag[$row][$element], "DATE") || $aTag[$row][$element] == "SYMBOL" || $aTag[$row][$element] == "NAME"){ // Date field, add quotes
					$query .= "'".$aVal[$row][$element]."', ";

				}else{
					if ($aVal[$row][$element] == ''){
						$query .= "NULL, ";
					}else{
						$query .= $aVal[$row][$element].", ";
					}
				}
			}
			$query = substr($query, 0, -2);
			$query .= ")";
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//echo $query."\r\n";
//break;
		}


		echo "Done\r\n";
		break;
}




//$parser = xml_parser_create();
//xml_parse_into_struct($parser, $output, $aValues, $aIndexes);
//xml_parser_free($parser);
//echo "Index array\n";
//print_r($aIndexes);
//echo "\nVals array\n";
//print_r($aValues);

////////////<error>unresponsive</error>
?>