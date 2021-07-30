<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on ports 22000 - 22499
Once connected to a port simply send it a pipe (|) delimited string of values to pass to an XML request via the Xserve web server.

/// Membership
	There are only two methods, one to create a new member and one to create a fund"
	- newManager - Create a new member in the old system (Do this when a new member joins)
		- Method (This tells the XML driven script which method to execute)
		- Login (Member's Portfolio login name)
		- Email (Member's email address)
		- Member ID (Member's ID from the new system)

		Example:
		- newManager|jeffsaunders|jeff.saunders@marketocracy.com|1

	- newFund - Create a new fund
		- Method (This tells the XML driven script which method to execute)
		- Login (Member's Portfolio login name)
		- Fund Type ("long" or "short")
		- Fund Name (The name to call it - must be unique for member but not across members)
		- Fund Symbol (3 or 4 character "ticker" symbol - must be unique for member but not across members)
		- Fund ID (The Fund ID assigned by the new system)

		Example:
		- newFund|jeffsaunders|long|New Fund|NEW1|1-1

	- updateSymbol - Change a fund's symbol
		- Method (This tells the XML driven script which method to execute)
		- Login (Member's Portfolio login name)
		- Fund ID (the fund ID from the new system)
		- Old/Current Fund Symbol (Current "ticker" symbol)
		- New Fund Symbol ("ticker" symbol to change to - must be unique for member but not across members)

		Example:
		- updateSymbol|jeffsaunders|1-2|JSF|NEW1

	- updateName - Change a fund's name
		- Method (This tells the XML driven script which method to execute)
		- Login (Member's Portfolio login name)
		- Fund ID (the fund ID from the new system)
		- Fund Symbol (the fund's "ticker" symbol)
		- New Fund Name (The new name for the fund)

		Example:
		- updateName|jeffsaunders|1-2|JSF|My Short Fund


/// Fund Data
    The string must be comma delimited with each element providing the following, in order:
	- Method (This tells the XML driven script which method to execute)
	- Login (Member's Portfolio login name)
	- Fund ID (the fund ID from the new system)
	- Symbol (Member's portfolio symbol)
	- Start Date (The date whose info you want, start date if it's a range, in YYYYMMDD format)
	- End Date (The end date if for a range, leave blank if only 1 day)

	Methods:
	- maxDate - get the last date the fundPrice server has data for
	- priceManager - starts the fund pricing process (only returns "sent", no completion notification)
	- livePrice - Live pricing for a given fund, to the minute
	- priceRun - get fund pricing information for a day or range of days (specify an end date to invoke range)
	- aggregateStatistics - get a fund's aggregate statistics for a given date
	- alphaBetaStatistics - get a fund's alphabeta statistics for a given date
	- positionDetail - get a fund's content details (stocks held) for a given date
	- positionInfo - get a specific stock within a fund's details, as of last closing date
	- tradesForPosition - get trade details for a stock, per fund, since inception
	- positionStratification - get basic stratification details for the stocks in a given fund
	- stylePositionStratification - get stratification details for the stocks in a given fund, by style
	- sectorPositionStratification - get stratification details for the stocks in a given fund, by sector

	Examples:
	- maxDate
	- priceManager|jeffsaunders
	- livePrice|jeffsaunders|1-1|JMF
	- priceRun|jeffsaunders|1-1|JMF|20140601
	- priceRun|jeffsaunders|1-1|JMF|20140530|20140601 (gets 3 day's worth)
	- aggregateStatistics|jeffsaunders|1-1|JMF|20140601
	- alphaBetaStatistics|jeffsaunders|1-1|JMF|20140601
	- positionDetail|jeffsaunders|1-1|JMF|20140601
	- positionInfo|jeffsaunders|1-1|JMF|AAPL
	- tradesForPosition|jeffsaunders|1-1|JMF|AAPL
	- positionStratification|jeffsaunders|1-1|JMF
	- stylePositionStratification|jeffsaunders|1-1|JMF
	- sectorPositionStratification|jeffsaunders|1-1|JMF

/// Stock Data
	There is only one method (so far)
	- stockInfo - get's current stock price info, including feed data
		- Method (This tells the XML driven script which method to execute)
		- Symbol (The "ticker" symbol for the stock)

		Example:
		- stockInfo|AAPL

*/

// Set up listener
$handle = fopen('php://stdin','r');
$input = fgets($handle, 1024);
fclose($handle);

$aInput = explode("|", $input);
//print_r($aInput);

// Assign passed method value
$method	= trim($aInput[0]);

// Build XML query string and assign proper CGI script for the passed method
switch ($method){

// Membership methods
	case "newManager":
		$login		= trim($aInput[1]);
		$email		= trim($aInput[2]);
		$memberID	= trim($aInput[3]);
		$cgiScript	= "manageradmin.cgi";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><email>".$email."</email></manageradmin>";
		break;

	case "newFund":
		$login		= trim($aInput[1]);
		$type		= trim($aInput[2]);
		$name		= trim(urlencode($aInput[3]));
		$symbol		= trim($aInput[4]);
		$fundID		= trim($aInput[5]);
		$cgiScript	= "manageradmin.cgi";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><longOrShort>".$type."</longOrShort><name>".$name."</name><fundSymbol>".$symbol."</fundSymbol></manageradmin>";
		break;

	case "updateSymbol":
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$oldSymbol	= trim($aInput[3]);
		$newSymbol	= trim($aInput[4]);
		$cgiScript	= "manageradmin.cgi";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><fundSymbol>".$oldSymbol."</fundSymbol><newSymbol>".$newSymbol."</newSymbol><fund_ID>".$fundID."</fund_ID></manageradmin>";
		break;

	case "updateName":
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$newName	= trim(urlencode($aInput[4]));
		$cgiScript	= "manageradmin.cgi";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><newName>".$newName."</newName><fund_ID>".$fundID."</fund_ID></manageradmin>";
		break;

// Fund Data methods


	case "maxDate2":
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>maxDate</method></fundPrice>";
		break;








	case "maxDate":
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method></fundPrice>";
		break;

	case "priceManager":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login></fundPrice>";
		break;

	case "livePrice":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$type		= ($end == "" ? "day" : "range");
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></fundPrice>";
		break;

	case "priceRun":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$start		= trim($aInput[4]);
		$end		= trim($aInput[5]);
		$type		= ($end == "" ? "day" : "range");
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><startDate>".$start."</startDate><endDate>".($type == "day" ? $start : $end)."</endDate></fundPrice>";
		break;

	case "aggregateStatistics":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$start		= trim($aInput[4]);
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date></fundPrice>";
		break;

	case "alphaBetaStatistics":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$start		= trim($aInput[4]);
		$cgiScript	= "fundprice.cgi";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date></fundPrice>";
		break;


// Stock Data method(s)
	case "positionDetail":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$start		= trim($aInput[4]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date></stockPrice>";
		break;

	case "positionInfo":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$stock		= trim($aInput[4]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$stock."</stockSymbol><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "tradesForPosition":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$stock		= trim($aInput[4]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$stock."</stockSymbol><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "positionStratification":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "stylePositionStratification":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "sectorPositionStratification":
		// Assign needed passed values
		$login		= trim($aInput[1]);
		$fundID		= trim($aInput[2]);
		$symbol		= trim($aInput[3]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "stockInfo":
		// Assign needed passed values
		$symbol		= trim($aInput[1]);
		$cgiScript	= "stockprice.cgi";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$symbol."</stockSymbol></stockPrice>";
		break;

//////////////////////////////////////////////// Need to add a default that throws an error, logs it, and aborts
//	default:

}
//echo $xmlString."\r\n";

// Build request
$url = preg_replace('/\s+/', ' ', trim("http://192.168.111.165/cgi-bin/".$cgiScript."?xmlString=".$xmlString));
echo $url."\r\n";

// Give it 5 tries if we get "unresponsive" back
for ($loop = 1; $loop <= 5; $loop++){

	if ($method == "maxDate2"){
		// Make sure the NFS mount is up
		if (!file_exists("/mnt/api/".$process."_processing")){
			exec("mount -a");
			sleep(1);
		}

		// Set some ground rules
		ob_implicit_flush();

		// Create a file
		$fp = fopen("/mnt/api/".$process."_processing/".$process."_input_".time().rand(0, 65535), "w");

		// Write the passed query string to the file
		fwrite($fp, $xmlString);

		// Close 'er up
		fclose($fp);

		break;
	}


	// Create a VERY random unique handle for each session
	$ch = "ch".time().rand(0, 65535);

	// create curl resource
	$$ch = curl_init();

	// set url
	curl_setopt($$ch, CURLOPT_URL, $url);

	// set curl options
	curl_setopt($$ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($$ch, CURLOPT_FORBID_REUSE, 1);
	curl_setopt($$ch, CURLOPT_FRESH_CONNECT, 1);

	// $output contains the output string
	$output = curl_exec($$ch);
//echo $output."\r\n";

	// close curl resource to free up system resources
	curl_close($$ch);

	// Create a VERY random unique name for the results array
	$aValues = "aValues".time().rand(0, 65535);

	// Parse the XML results for DB insertion
	$parser = xml_parser_create();
	xml_parse_into_struct($parser, $output, $$aValues);
	xml_parser_free($parser);
print_r($$aValues);

	//if we don't get "unresponsive" then bail out of the loop and process the response
	if (${$aValues}[0]['value'] != "unresponsive"){
		break;
	}
}

// Connect to MySQL
$dbHost = "192.168.111.211";
$dbUser = "frontbasefetcher";
//$dbPass = "KfabyZcbE3"; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbPass = ""; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbName = "marketocracy";
$linkID = mysql_connect($dbHost, $dbUser, $dbPass) or die("Could not connect to MySQL");
mysql_select_db($dbName, $linkID) or die("Could not select ".$dbName." DB in MySQL");

// Get newest system config values
$query = "
	SELECT *
	FROM system_config conf
	INNER JOIN (
		SELECT max(uid) AS uid, setting
		FROM system_config
		GROUP BY setting
	) dup ON dup.setting = conf.setting
	WHERE conf.uid = dup.uid
";
$rs_config = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Create variables from the stored settings and assign the stored values to them
while ($config = mysql_fetch_assoc($rs_config)){
	$var = trim($config['setting']); // Just in case there are spaces before or after the value
	$$var = trim($config['value']);  // Create the var based on the "setting" value, and assign the actual "value" value to it
//echo $var." -> ".$$var."\r\n";
}

//...and database table definitions
$query = "
	SELECT *
	FROM system_config_database conf
	INNER JOIN (
		SELECT max(uid) AS uid, setting
		FROM system_config_database
		GROUP BY setting
	) dup ON dup.setting = conf.setting
	WHERE conf.uid = dup.uid
";
$rs_config = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Create variables from the stored settings and assign the stored values to them
while ($config = mysql_fetch_assoc($rs_config)){
	$var = trim($config['setting']); // Just in case there are spaces before or after the value
	$$var = trim($config['value']);  // Create the var based on the "setting" value, and assign the actual "value" value to it
//echo $var." -> ".$$var."\r\n";
}

// Check for errors (including 6th consecutive "unresponsive")
//if (strpos($output, "<H1>Forbidden</H1>")){
//	//Write error message to system_fetch_errors
//	$query =	"INSERT INTO ".$fetch_errors_table." (
//					timestamp,
//					input,
//					query,
//					error
//				) VALUES (
//					UNIX_TIMESTAMP(),
//					'".addslashes($input)."',
//					'".addslashes($url)."',
//					'".addslashes($output)."'
//				)";
//	$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//	echo "Aborted - Error logged";
//}else if(${$aValues}[0]['tag'] == "ERROR"){
if (${$aValues}[0]['tag'] != "RESULTS"){
	//Write error message to system_fetch_errors
//	$query =	"INSERT INTO ".$fetch_errors_table." (
//					timestamp,
//					input,
//					query,
//					error
//				) VALUES (
//					UNIX_TIMESTAMP(),
//					'".addslashes($input)."',
//					'".addslashes($url)."',
//					'".addslashes(${$aValues}[0]['value'])."'
//				)";
	$query =	"INSERT INTO ".$fetch_errors_table." (
					timestamp,
					input,
					query,
					error
				) VALUES (
					UNIX_TIMESTAMP(),
					'".addslashes($input)."',
					'".addslashes($url)."',
					'".addslashes($output)."'
				)";
	$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	echo "Aborted - Error logged";
}else{ // No errors, process the response
	// Process the response depending on the method specified
	switch ($method){

		// Membership
		case "newManager":
			$primaryKey		= ${$aValues}[1]['value']; // returns the unique key assigned to the new member
			$portfolioKey	= ${$aValues}[2]['value']; // returns the new first portfolio key assigned to the new member
//echo $primaryKey."\r\n";
			// Now insert the key into the new member's membership record
			$query =	"UPDATE ".$members_table."
						 SET	fb_primarykey = 'X\'".$primaryKey."\'',
						 		fb_portfoliokey = 'X\'".$portfolioKey."\''
						 WHERE member_id = ".$memberID
						;
//echo $query."\r\n";
			$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;


		case "newFund":
			$primaryKey = ${$aValues}[1]['value']; // returns the unique key assigned to the new fund
//echo $primaryKey."\r\n";
			// Now insert the key into the member's fund record
			$query =	"UPDATE ".$fund_table."
						 SET fb_primarykey = 'X\'".$primaryKey."\''
						 WHERE fund_id = '".$fundID."'
						";
//echo $query."\r\n";
			$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;


		case "updateSymbol":
			// Nothing to do...
			echo "Success";
			break;


		case "updateName":
			// Nothing to do...
			echo "Success";
			break;


		// Fund Info
		case "maxDate":
			$maxDate = ${$aValues}[1]['value']; // returns YYYYMMDD
//echo $maxDate."\r\n";
			if ($maxDate != ""){
				// Write maxDate to members_fund_maxdate (overwrite old value)
				$query =	"UPDATE ".$fund_maxdate_table."
							 SET timestamp = UNIX_TIMESTAMP(),
							 maxdate = '".$maxDate."'
							 WHERE 1
							";
//echo $query."\r\n";
				$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;


		case "priceManager":
			$priceManager = ${$aValues}[1]['value']; // returns "sent" if successful
			// Now do something with it...
			// Not sure what to do here, if anything.........
//			echo $priceManager."\r\n";
			echo "Success";
			break;


		case "livePrice":
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

					// Create VERY unique names for the two placeholder arrays
					$aTag = "aTag".time().rand(0, 65535);
					$aVal = "aVal".time().rand(0, 65535);

					// Initialize arrays
					$$aTag = array();
					$$aVal = array();
				}
				if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push($$aTag, ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
					if (strpos($value, "e")){ // Exponential number
						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
					}
					// Push the value onto the values array
					array_push($$aVal, $value);
					if (${$aValues}[$element]['tag'] == "FUND_ID"){
						// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
						// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
						$fundID = trim(''.$value);
					}
				}
			}
			// Check to see if the fund is already in the table
			$query =	"SELECT *
						 FROM ".$fund_liveprice_table."
						 WHERE fund_id = '".$fundID."'
						";
			$rs_select = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			// WOW!  It isn't (SURPRISE!)  Let's add it.
			if (mysql_num_rows($rs_select) == 0){
				$query = "INSERT INTO ".$fund_liveprice_table." (timestamp, ";
				// Tack on all the tag names
				for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
					$query .= ${$aTag}[$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				// Now add the values
				$query .= ") VALUES (UNIX_TIMESTAMP(), ";
				// Tack on all the values
				for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
					if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
						$query .= "'".${$aVal}[$element]."', ";
					}else{
						if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
							$query .= "NULL, ";
						}else{
							$query .= ${$aVal}[$element].", ";
						}
					}
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				$query .= ")";
//echo $query."\r\n";
				// Do it!
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}else{  // It is there, just update it
				$query = "UPDATE ".$fund_liveprice_table." SET timestamp = UNIX_TIMESTAMP(), ";

				for ($element = 0; $element < sizeof($$aTag); $element++){
					if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
						$query .= ${$aTag}[$element]." = '".${$aVal}[$element]."', ";
					}else{
						if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
							$query .= ${$aTag}[$element]." = NULL, ";
						}else{
							$query .= ${$aTag}[$element]." = ".${$aVal}[$element].", ";
						}
					}
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				$query .= " WHERE fund_id = '".$fundID."'";
//echo $query."\r\n";
				$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;


		case "priceRun":
			$day = 0; // In case there is more than one day

			// Create VERY unique names for the two placeholder arrays
			$aTag = "aTag".time().rand(0, 65535);
			$aVal = "aVal".time().rand(0, 65535);

			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
					$fundID = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 2){ // About to start data

					// Initialize arrays
					${$aTag}[$day] = array();
					${$aVal}[$day] = array();
				}
				if (${$aValues}[$element]['level'] == 3){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push(${$aTag}[$day], ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
					if (strpos($value, "e")){ // Exponential number
						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
					}
					// Push the value onto the values array
					array_push(${$aVal}[$day], $value);
				}
				if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 2){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\r\n\n";
					// Increment the day counter
					$day++;
				}
			}
//print_r($$aVal);
			// Now build the query to insert the data
			for ($row = 0; $row < sizeof($$aTag); $row++){
				$query = "INSERT INTO ".$fund_pricing_table." (fund_id, timestamp, unix_date, ";
				// Tack on all the tag names
				for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
					$query .= ${$aTag}[$row][$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				// Now add the values
				$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[$row][0], 4, 2), substr(${$aVal}[$row][0], 6, 2), substr(${$aVal}[$row][0], 0, 4)).", ";
				// Tack on all the values
				for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
					$query .= ${$aVal}[$row][$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				$query .= ")";
//echo $query."\r\n";
				// Do it!
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;


		case "aggregateStatistics":
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

					// Create VERY unique names for the two placeholder arrays
					$aTag = "aTag".time().rand(0, 65535);
					$aVal = "aVal".time().rand(0, 65535);

					// Initialize arrays
					$$aTag = array();
					$$aVal = array();
				}
				if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push($$aTag, ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
					if (strpos($value, "e")){ // Exponential number
						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
					}
					// Push the value onto the values array
					array_push($$aVal, $value);
					if (${$aValues}[$element]['tag'] == "FUND_ID"){
						// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
						// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
						$fundID = trim(''.$value);
					}
				}
			}

			// Delete all the existing records - we are replacing them with fresh ones
			$query = "	DELETE FROM ".$fund_aggregate_table."
						WHERE fund_id = '".$fundID."'
			";
			$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// Now build the query to insert the data
			$query = "INSERT INTO ".$fund_aggregate_table." (timestamp, unix_date, ";
			// Tack on all the tag names
			for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
				$query .= ${$aTag}[$element].", ";
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			// Now add the values
			$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
			// Tack on all the values
			for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
				if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
					$query .= "'".${$aVal}[$element]."', ";
				}else{
					if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
						$query .= "NULL, ";
					}else{
						$query .= ${$aVal}[$element].", ";
					}
				}
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			$query .= ")";
//echo $query."\r\n";
			// Do it!
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;


		case "alphaBetaStatistics":
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

					// Create VERY unique names for the two placeholder arrays
					$aTag = "aTag".time().rand(0, 65535);
					$aVal = "aVal".time().rand(0, 65535);

					// Initialize arrays
					$$aTag = array();
					$$aVal = array();
				}
				if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push($$aTag, ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
					if (strpos($value, "e")){ // Exponential number
						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
					}
					// Push the value onto the values array
					array_push($$aVal, $value);
					if (${$aValues}[$element]['tag'] == "FUND_ID"){
						// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
						// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
						$fundID = trim(''.$value);
					}
				}
			}

			// Delete all the existing records - we are replacing them with fresh ones
			$query = "	DELETE FROM ".$fund_alphabeta_table."
						WHERE fund_id = '".$fundID."'
			";
			$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// Now build the query to insert the data
			$query = "INSERT INTO ".$fund_alphabeta_table." (timestamp, unix_date, ";
			// Tack on all the tag names
			for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
				$query .= ${$aTag}[$element].", ";
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			// Now add the values
			$query .= ") VALUES (UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[1], 4, 2), substr(${$aVal}[1], 6, 2), substr(${$aVal}[1], 0, 4)).", ";
			// Tack on all the values
			for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
				if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
//				if (strpos(${$aTag}[$element], "DATE")){ // Date field, add quotes
					$query .= "'".${$aVal}[$element]."', ";
				}else{
					if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
						$query .= "NULL, ";
					}else{
						$query .= ${$aVal}[$element].", ";
					}
				}
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			$query .= ")";
//echo $query."\r\n";
			// Do it!
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;


		case "positionDetail":
			$stock = 0;

			// Create VERY unique names for the two placeholder arrays
			$aTag = "aTag".time().rand(0, 65535);
			$aVal = "aVal".time().rand(0, 65535);

			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['tag'] == "FUND_ID"){
					$fundID = ${$aValues}[$element]['value'];
				}
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 3){ // About to start data

					// Initialize arrays
					${$aTag}[$stock] = array();
					${$aVal}[$stock] = array();
				}
				if (${$aValues}[$element]['level'] == 4){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push(${$aTag}[$stock], ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
					// Push the value onto the values array
					array_push(${$aVal}[$stock], $value);
				}
				if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 3){ // Passed end of data
//print_r($aTag[$stock]);
//print_r($aVal[$stock]);
//echo "\r\n\n";
					$stock++;
				}
			}

			// If there are no positions, write a dummy record that says so and bail
			if (sizeof($$aTag) < 1){
				$query =	"INSERT INTO ".$fund_positions_table." (
							fund_id,
							timestamp,
							unix_date,
							date,
							company_id,
							stockSymbol,
							name
						) VALUES (
							'".$fundID."',
							UNIX_TIMESTAMP(),
							".mktime(0, 0, 0, substr($start, 4, 2), substr($start, 6, 2), substr($start, 0, 4)).",
							".$start.",
							'-1',
							'99999',
							'No Positions'
						)";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
				break;
			}

			// Now build the query to insert the data
			for ($row = 0; $row < sizeof($$aTag); $row++){

				// Get the stock ID so we can store it with each fund details record
				$stockSymbol = trim(''.${$aVal}[$row][0]);
				$query = "	SELECT company_id
							FROM ".$stock_prices_table."
							WHERE symbol = '".$stockSymbol."'
							ORDER BY timestamp DESC
							LIMIT 1
				";
				$rs_companyID = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				// If the stock is missing, go get it
				if (mysql_num_rows($rs_companyID) == 0){
					$query = "stockInfo|".$stockSymbol;
//echo $query."<br>";

					// Set the port number for the API call
					$port = rand(22000, 22499);

					// Execute the query call (call myself on another port)
					exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

					// Wait a tick (or 10!) to give it time to finish getting the data
					sleep(10);

					// Query it again now that we *should have it in the system
					$query = "	SELECT company_id
								FROM ".$stock_prices_table."
								WHERE symbol = '".$stockSymbol."'
								ORDER BY timestamp DESC
								LIMIT 1
					";
					$rs_companyID = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
				}
				$companyInfo = mysql_fetch_assoc($rs_companyID);
				$companyID = $companyInfo['company_id'];
//echo $companyID."\n";

				// Now build the query to insert the data
				$query = "INSERT INTO ".$fund_positions_table." (fund_id, timestamp, unix_date, date, company_id, ";
				// Tack on all the tag names
				for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
					$query .= ${$aTag}[$row][$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				// Now add the values
				$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr($start, 4, 2), substr($start, 6, 2), substr($start, 0, 4)).", ".$start.", '".$companyID."', ";
				// Tack on all the values
				for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
					if (strpos(${$aTag}[$row][$element], "DATE") || ${$aTag}[$row][$element] == "STOCKSYMBOL" || ${$aTag}[$row][$element] == "NAME"){ // Character fields, add quotes
						$query .= "'".${$aVal}[$row][$element]."', ";
					}else{
						if (${$aVal}[$row][$element] == ''){ // Blank decimal value, set it to NULL
							$query .= "NULL, ";
						}else{
							$query .= ${$aVal}[$row][$element].", ";
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
			echo "Success";
			break;


		case "positionInfo":
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

					// Create VERY unique names for the two placeholder arrays
					$aTag = "aTag".time().rand(0, 65535);
					$aVal = "aVal".time().rand(0, 65535);

					// Initialize arrays
					$$aTag = array();
					$$aVal = array();
				}
				if (${$aValues}[$element]['level'] == 2){ // There be data here...
//echo $${$aValues}[$element]['tag'] . " => " . $${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push($$aTag, ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
//					if (strpos($value, "e")){ // Exponential number
//						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//					}
					// Push the value onto the values array
					array_push($$aVal, $value);
					if (${$aValues}[$element]['tag'] == "FUND_ID"){
						// Add a space then take it right back off to force it to be seen as a string (i.e. "1-1")
						// strval() insists on doing the math first!  strval(1-1) yields "0" as a string, not "1-1".
						$fundID = trim(''.$value);
					}
				}
			}
//print_r($$aTag);print_r($$aVal);//die();

			// Delete all the existing records - we are replacing them with fresh ones
			$query = "	DELETE FROM ".$fund_positions_details_table."
						WHERE fund_id = '".$fundID."'
						AND stockSymbol = '".$stockSymbol."'
			";
			$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// Now build the query to insert the data
			$query = "INSERT INTO ".$fund_positions_details_table." (timestamp, first_trade_unix_date, last_trade_unix_date, ";
			// Tack on all the tag names
			for ($element = 0; $element < sizeof($$aTag); $element++){
//echo $aTag[$element]."\r\n";
				$query .= ${$aTag}[$element].", ";
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			// Now add the values
			$query .= ") VALUES (UNIX_TIMESTAMP(), ".strtotime(${$aVal}[13]).", ".strtotime(${$aVal}[14]).", "; // 13 & 14 are firstTradeTimestamp and lastTradeTimestamp resp.
			// Tack on all the values
			for ($element = 0; $element < sizeof($$aVal); $element++){
//echo $aVal[$element]."\r\n";
//				if (strpos(${$aTag}[$element], "UND_ID") || strpos(${$aTag}[$element], "DATE")){ // ID and Date fields, add quotes
//				if (strpos(${$aTag}[$element], "DATE")){ // Date field, add quotes
//					$query .= "'".${$aVal}[$element]."', ";
//				}else{
					if (${$aVal}[$element] == ''){ // Blank decimal value, set it to NULL
						$query .= "NULL, ";
					}else{
//						$query .= ${$aVal}[$element].", ";
						$query .= "'".${$aVal}[$element]."', ";
					}
//				}
			}
			// Pop the trailing ", " off
			$query = substr($query, 0, -2);
			$query .= ")";
//echo $query."\r\n";
//die();
			// Do it!
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;


		case "tradesForPosition":
			$day = 0; // In case there is more than one day

			// Create VERY unique names for the two placeholder arrays
			$aTag = "aTag".time().rand(0, 65535);
			$aVal = "aVal".time().rand(0, 65535);

			// Parse the data
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
					$fundID = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['tag'] == "STOCKSYMBOL" && ${$aValues}[$element]['level'] == 2){ // Returned Stock Symbol
					$stockSymbol = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 2){ // About to start data

					// Initialize arrays
					${$aTag}[$day] = array();
					${$aVal}[$day] = array();
				}
				if (${$aValues}[$element]['level'] == 3){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push(${$aTag}[$day], ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
//					if (strpos($value, "e")){ // Exponential number
//						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//					}
					// Push the value onto the values array
					array_push(${$aVal}[$day], $value);
				}
				if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 2){ // Passed end of data
//print_r($aTag[$day]);
//print_r($aVal[$day]);
//echo "\r\n\n";
					// Increment the day counter
					$day++;
				}
			}
//print_r($$aVal);

			// Get the stock ID so we can store it with each record
			$query = "	SELECT company_id
						FROM ".$stock_prices_table."
						WHERE symbol = '".$stockSymbol."'
						ORDER BY timestamp DESC
						LIMIT 1
			";
			$rs_companyID = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// If the stock is missing, go get it
			if (mysql_num_rows($rs_companyID) == 0){
				$query = "stockInfo|".$stockSymbol;
//echo $query."<br>";

				// Set the port number for the API call
				$port = rand(22000, 22499);

				// Execute the query call (call myself on another port)
				exec('/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

				// Wait a tick (or 10!) to give it time to finish getting the data
				sleep(10);

				// Query it again now that we *should have it in the system
				$query = "	SELECT company_id
							FROM ".$stock_prices_table."
							WHERE symbol = '".$stockSymbol."'
							ORDER BY timestamp DESC
							LIMIT 1
				";
				$rs_companyID = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			$companyInfo = mysql_fetch_assoc($rs_companyID);
			$companyID = $companyInfo['company_id'];
//echo $companyID."\n";

			// Delete all the existing records - we are replacing them with fresh ones
			$query = "	DELETE FROM ".$fund_trades_table."
						WHERE fund_id = '".$fundID."'
						AND stockSymbol = '".$stockSymbol."'
			";
			$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// Now build the query to insert the data
			for ($row = 0; $row < sizeof($$aTag); $row++){
				$query = "INSERT INTO ".$fund_trades_table." (fund_id, timestamp, unix_opened, unix_closed, company_id, stockSymbol, ";
				// Tack on all the tag names
				for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
					$query .= ${$aTag}[$row][$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				// Now add the values
				$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), ".mktime(0, 0, 0, substr(${$aVal}[$row][0], 4, 2), substr(${$aVal}[$row][0], 6, 2), substr(${$aVal}[$row][0], 0, 4)).", ".mktime(0, 0, 0, substr(${$aVal}[$row][1], 4, 2), substr(${$aVal}[$row][1], 6, 2), substr(${$aVal}[$row][1], 0, 4)).", ".$companyID.", '".$stockSymbol."', ";
				// Tack on all the values
				for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
					$query .= "'".${$aVal}[$row][$element]."', ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				$query .= ")";
//echo $query."\r\n";
				// Do it!
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;


		case "positionStratification":
			$position = 0; // In case there is more than one day

			// Get the stratification codes and stuff them into an array
			$query = "	SELECT *
						FROM ".$stock_stratification_codes_table."
			";
			$rs_codes = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// Create arrays to hold stratification codes
			$aStyle	 = array();
			$aSector = array();

			// Stuff the arrays
			while ($codes = mysql_fetch_assoc($rs_codes)){
				if ($codes['style'] !== NULL){
					$aStyle[$codes['style']] = $codes['style_code'];
				}
				if ($codes['sector'] !== NULL){
					$aSector[$codes['sector']] = $codes['sector_code'];
				}
			}
//print_r($aStyle);
//print_r($aSector);
//die();

			// Create VERY unique names for the two data placeholder arrays
			$aTag = "aTag".time().rand(0, 65535);
			$aVal = "aVal".time().rand(0, 65535);

			// Parse the data
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
					$fundID = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['tag'] == "STOCKSYMBOL" && ${$aValues}[$element]['level'] == 4){ // Returned Stock Symbol
					$stockSymbol = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 3){ // About to start data

					// Initialize arrays
					${$aTag}[$position] = array();
					${$aVal}[$position] = array();
				}
				if (${$aValues}[$element]['level'] == 4){ // There be data here...
//echo ${$aValues}[$element]['tag'] . " => " . ${$aValues}[$element]['value'] . "\r\n";
					// Push the tag name onto the tags array
					array_push(${$aTag}[$position], ${$aValues}[$element]['tag']);
					$value = addslashes(${$aValues}[$element]['value']);
//					if (strpos($value, "e")){ // Exponential number
//						$value = number_format(${$aValues}[$element]['value'], 0, '', ''); // Convert to a real number
//					}
					// Push the value onto the values array
					array_push(${$aVal}[$position], $value);

					// Store off the Sector and Style values into their own arrays for later use
					if (${$aValues}[$element]['tag'] == "SECTOR"){ // Sector value
						$sector[$position] = trim(''.${$aValues}[$element]['value']);
					}
					if (${$aValues}[$element]['tag'] == "STYLE"){ // Style value
						$style[$position] = trim(''.${$aValues}[$element]['value']);
					}
				}
				if (${$aValues}[$element]['type'] == "close" && ${$aValues}[$element]['level'] == 3){ // Passed end of data
//print_r($aTag[$position]);
//print_r($aVal[$position]);
//echo "\r\n\n";
					// Increment the day counter
					$position++;
				}
			}
//print_r($$aVal);

			// Delete all the existing records - we are replacing them with fresh ones
			$query = "	DELETE FROM ".$fund_stratification_basic_table."
						WHERE fund_id = '".$fundID."'
			";
			$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			// If there are no positions, write a dummy record that says so and bail
			if (sizeof($$aTag) < 1){
				$query =	"INSERT INTO ".$fund_stratification_basic_table." (
							fund_id,
							timestamp,
							stockSymbol,
							label
						) VALUES (
							'".$fundID."',
							UNIX_TIMESTAMP(),
							'99999',
							'No Positions'
						)";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
				break;
			}

			// Now build the query to insert the data
			for ($row = 0; $row < sizeof($$aTag); $row++){
				$query = "INSERT INTO ".$fund_stratification_basic_table." (fund_id, timestamp, sector_code, style_code, ";
				// Tack on all the tag names
				for ($element = 0; $element < sizeof(${$aTag}[$row]); $element++){
//echo $aTag[$row][$element]."\r\n";
					$query .= ${$aTag}[$row][$element].", ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				// Now add the values
				$query .= ") VALUES ('".$fundID."', UNIX_TIMESTAMP(), '".$aSector[$sector[$row]]."', '".$aStyle[$style[$row]]."',";
				// Tack on all the values
				for ($element = 0; $element < sizeof(${$aVal}[$row]); $element++){
//echo $aVal[$row][$element]."\r\n";
					$query .= "'".${$aVal}[$row][$element]."', ";
				}
				// Pop the trailing ", " off
				$query = substr($query, 0, -2);
				$query .= ")";
//echo $query."\r\n";
				// Do it!
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;


		case "stylePositionStratification":
			$stylesCounter = 0;
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['tag'] == "FUND_ID"){ // Returned FundID

					// Set the fund ID
					$fundID = trim(''.${$aValues}[$element]['value']);

					// Delete all the existing records - we are replacing them with fresh ones
					$query = "	DELETE FROM ".$fund_stratification_style_table."
								WHERE fund_id = '".$fundID."'
					";
					$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

					// Delete the old positions too
					$query = "	DELETE FROM ".$fund_stratification_style_positions_table."
								WHERE fund_id = '".$fundID."'
					";
					$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
				if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "open"){ // About to start n(nother) style slice

					// Initialize style arrays
					$aSTag = array();
					$aSVal = array();

					// Set the style ID
					$stylesCounter++;
					$styleID = $fundID."-".$stylesCounter;

				}
				if (${$aValues}[$element]['level'] == 4 && ${$aValues}[$element]['type'] == "complete"){ // There be style data here...

					// Push the tag name onto the tags array
					array_push($aSTag, ${$aValues}[$element]['tag']);

					// Push the value onto the values array
					$value = addslashes(${$aValues}[$element]['value']);
					array_push($aSVal, $value);

				}
				if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "open"){ // We're starting a(nother) position

					// Initialize position arrays
					$aPTag = array();
					$aPVal = array();

				}
				if (${$aValues}[$element]['level'] == 6 && ${$aValues}[$element]['type'] == "complete"){ // There be position data here...

					// Push the tag name onto the tags array
					array_push($aPTag, ${$aValues}[$element]['tag']);

					// Push the value onto the values array
					$value = addslashes(${$aValues}[$element]['value']);
					array_push($aPVal, $value);
				}
				if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position
//print_r($aPTag);
//print_r($aPVal);
//echo $styleID;
//echo "\n\n";
					// Build the query to insert the positions data for this style
					$query = "INSERT INTO ".$fund_stratification_style_positions_table." (fund_id, style_id, timestamp, ";
					// Tack on all the tag names
					for ($column = 0; $column < sizeof($aPTag); $column++){
						$query .= $aPTag[$column].", ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					// Now add the values
					$query .= ") VALUES ('".$fundID."', '".$styleID."', UNIX_TIMESTAMP(), ";
					// Tack on all the values
					for ($value = 0; $value < sizeof($aPVal); $value++){
						$query .= "'".$aPVal[$value]."', ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					$query .= ")";
//echo $query."\r\n";
					// Do it!
					$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
				if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position
//print_r($aSTag);
//print_r($aSVal);
//echo sizeof($aSTag);
//echo "\n\n";
					// Build the query to insert the style data
					$query = "INSERT INTO ".$fund_stratification_style_table." (fund_id, style_id, timestamp, ";
					// Tack on all the tag names
					for ($column = 0; $column < sizeof($aSTag); $column++){
						$query .= $aSTag[$column].", ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					// Now add the values
					$query .= ") VALUES ('".$fundID."', '".$styleID."', UNIX_TIMESTAMP(), ";
					// Tack on all the values
					for ($value = 0; $value < sizeof($aSVal); $value++){
						$query .= "'".$aSVal[$value]."', ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					$query .= ")";
//echo $query."\r\n";
					// Do it!
					$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
			}
			// If there are no styles, write a dummy record that says so and bail
			if ($stylesCounter < 1){
				$query =	"INSERT INTO ".$fund_stratification_style_table." (
							fund_id,
							style_id,
							timestamp,
							styleName
						) VALUES (
							'".$fundID."',
							'".$fundID."-99999',
							UNIX_TIMESTAMP(),
							'No Positions'
						)";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			break;


		case "sectorPositionStratification":
			$sectorsCounter = 0;
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				if (${$aValues}[$element]['level'] == 2 && ${$aValues}[$element]['tag'] == "FUND_ID"){ // Returned FundID

					// Set the fund ID
					$fundID = trim(''.${$aValues}[$element]['value']);

					// Delete all the existing records - we are replacing them with fresh ones
					$query = "	DELETE FROM ".$fund_stratification_sector_table."
								WHERE fund_id = '".$fundID."'
					";
					$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

					// Delete the old positions too
					$query = "	DELETE FROM ".$fund_stratification_sector_positions_table."
								WHERE fund_id = '".$fundID."'
					";
					$rs_delete = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
				if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "open"){ // About to start n(nother) style slice

					// Initialize style arrays
					$aSTag = array();
					$aSVal = array();

					// Set the style ID
					$sectorsCounter++;
					$sectorID = $fundID."-".$sectorsCounter;

				}
				if (${$aValues}[$element]['level'] == 4 && ${$aValues}[$element]['type'] == "complete"){ // There be sector data here...

					// Push the tag name onto the tags array
					array_push($aSTag, ${$aValues}[$element]['tag']);

					// Push the value onto the values array
					$value = addslashes(${$aValues}[$element]['value']);
					array_push($aSVal, $value);

				}
				if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "open"){ // We're starting a(nother) position

					// Initialize position arrays
					$aPTag = array();
					$aPVal = array();

				}
				if (${$aValues}[$element]['level'] == 6 && ${$aValues}[$element]['type'] == "complete"){ // There be position data here...

					// Push the tag name onto the tags array
					array_push($aPTag, ${$aValues}[$element]['tag']);

					// Push the value onto the values array
					$value = addslashes(${$aValues}[$element]['value']);
					array_push($aPVal, $value);
				}
				if (${$aValues}[$element]['level'] == 5 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position
//print_r($aPTag);
//print_r($aPVal);
//echo $styleID;
//echo "\n\n";
					// Build the query to insert the positions data for this sector
					$query = "INSERT INTO ".$fund_stratification_sector_positions_table." (fund_id, sector_id, timestamp, ";
					// Tack on all the tag names
					for ($column = 0; $column < sizeof($aPTag); $column++){
						$query .= $aPTag[$column].", ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					// Now add the values
					$query .= ") VALUES ('".$fundID."', '".$sectorID."', UNIX_TIMESTAMP(), ";
					// Tack on all the values
					for ($value = 0; $value < sizeof($aPVal); $value++){
						$query .= "'".$aPVal[$value]."', ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					$query .= ")";
//echo $query."\r\n";
					// Do it!
					$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
				if (${$aValues}[$element]['level'] == 3 && ${$aValues}[$element]['type'] == "close"){ // We're at the end of the position
//print_r($aSTag);
//print_r($aSVal);
//echo sizeof($aSTag);
//echo "\n\n";
					// Build the query to insert the sector data
					$query = "INSERT INTO ".$fund_stratification_sector_table." (fund_id, sector_id, timestamp, ";
					// Tack on all the tag names
					for ($column = 0; $column < sizeof($aSTag); $column++){
						$query .= $aSTag[$column].", ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					// Now add the values
					$query .= ") VALUES ('".$fundID."', '".$sectorID."', UNIX_TIMESTAMP(), ";
					// Tack on all the values
					for ($value = 0; $value < sizeof($aSVal); $value++){
						$query .= "'".$aSVal[$value]."', ";
					}
					// Pop the trailing ", " off
					$query = substr($query, 0, -2);
					$query .= ")";
//echo $query."\r\n";
					// Do it!
					$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				}
			}
			// If there are no sectors, write a dummy record that says so and bail
			if ($sectorsCounter < 1){
				$query =	"INSERT INTO ".$fund_stratification_sector_table." (
							fund_id,
							sector_id,
							timestamp,
							sectorName
						) VALUES (
							'".$fundID."',
							'".$fundID."-99999',
							UNIX_TIMESTAMP(),
							'No Positions'
						)";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			break;


		// Stock Info
		case "stockInfo":
			for ($element = 0; $element <= sizeof($$aValues); $element++){
				// This currently comes in at the END of the XML results so it wouldn't find it until after traversing the entire result set.
				// Therefore it's not very useful but since the FundID is not actually used for anything yet it's OK.
				// I just put this code here for future use....
				if (${$aValues}[$element]['tag'] == "FUND_ID" && ${$aValues}[$element]['level'] == 2){ // Returned FundID
					$fundID = trim(''.${$aValues}[$element]['value']);
				}
				if (${$aValues}[$element]['type'] == "open" && ${$aValues}[$element]['level'] == 1){ // About to start data

					// Create VERY unique name for the placeholder array
					$aStockInfo = "aStockInfo".time().rand(0, 65535);

					// Initialize arrays
					$$aStockInfo = array();
				}
				if (${$aValues}[$element]['level'] == 2){ // There be data here...
					${$aStockInfo}[${$aValues}[$element]['tag']] = addslashes(${$aValues}[$element]['value']);
				}
			}
//print_r($aStockInfo);

			// See if the company is already in the stock_companies table (probably is)
			$query =	"SELECT *
						 FROM ".$stock_companies_table."
						 WHERE company_name = '".${$aStockInfo}['NAME']."'
						";
			$rs_select = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			// WOW!  It isn't (SURPRISE!)  Let's add it.
			if (mysql_num_rows($rs_select) == 0){
				$query =	"INSERT INTO ".$stock_companies_table." (
								company_id,
								company_name,
								timestamp
							 ) SELECT COALESCE((SELECT MAX(company_id) FROM ".$stock_companies_table."), 0) + 1,
							 '".${$aStockInfo}['NAME']."',
							 UNIX_TIMESTAMP()
							";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
				// Read it back to get the new company_id
				$query =	"SELECT *
							 FROM ".$stock_companies_table."
							 WHERE company_name = '".${$aStockInfo}['NAME']."'
							";
				$rs_select = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			$company = mysql_fetch_assoc($rs_select);
			$companyID = $company['company_id'];

			// See if the stock symbol is already in the stock_symbols table (probably is)
			$query =	"SELECT *
						 FROM ".$stock_symbols_table."
						 WHERE symbol = '".$symbol."'
						";
			$rs_select = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			// WOW!  It isn't (SURPRISE!)  Let's add it.
			if (mysql_num_rows($rs_select) == 0){
				$query =	"INSERT INTO ".$stock_symbols_table." (
								company_id,
								symbol,
								exchange,
								sector,
								style,
								timestamp
							) VALUES (
								".$companyID.",
								'".$symbol."',
								'".${$aStockInfo}['EXCHANGE']."',
								'".${$aStockInfo}['SECTOR']."',
								'".${$aStockInfo}['STYLE']."',
								UNIX_TIMESTAMP()
							)";
//echo $query."\r\n";
				$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}

			// Insert the current price info
			$query =	"INSERT INTO ".$stock_prices_table." (
							company_id,
							symbol,
							timestamp,
							currentPrice,
							lastPrice,
							netChange,
							todayReturn,
							moving50DayAvgClosed,
							moving200DayAvgClosed,
							oneYearHigh,
							dayHigh,
							oneYearLow,
							dayLow,
							netVolume,
							moving50DayAvgVolume,
							shortVolume,
							marketcap
						) VALUES (
							".$companyID.",
							'".$symbol."',
							UNIX_TIMESTAMP(),
							".${$aStockInfo}['CURRENTPRICE'].",
							".${$aStockInfo}['LASTPRICE'].",
							".${$aStockInfo}['NETCHANGE'].",
							".${$aStockInfo}['TODAYRETURN'].",
							".${$aStockInfo}['MOVING50DAYAVGCLOSED'].",
							".${$aStockInfo}['MOVING200DAYAVGCLOSED'].",
							".${$aStockInfo}['ONEYEARHIGH'].",
							".${$aStockInfo}['DAYHIGH'].",
							".${$aStockInfo}['ONEYEARLOW'].",
							".${$aStockInfo}['DAYLOW'].",
							".${$aStockInfo}['NETVOLUME'].",
							".${$aStockInfo}['MOVING50DAYAVGVOLUME'].",
							".${$aStockInfo}['SHORTVOLUME'].",
							".${$aStockInfo}['MARKETCAP']."
						)";
//echo $query."\r\n";
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;
	}
}

?>