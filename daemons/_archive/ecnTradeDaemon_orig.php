<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on port 33333
Once connected to port 33333 simply send it a pipe (|) delimited string of values to pass to an XML request via the Xserve web server.

The string must be comma delimited with each element providing the following, in order:

	- Method (This tells the XML driven script which method to execute)
	- Key (This is the key relevant to the method - the Fund key if creating a ticket, the Ticket key if checking status, etc.  Leave blank is N/A)
	- Login (Member's Portfolio login name.  Leave blank if N/A)
	- Fund Symbol (Member's portfolio symbol,  Leave blank if N/A)
	- Fund ID (the fund ID from the new system.  Leave blank if N/A)
	- Action (If creating a ticket what do you want to do, "buy" or "sell"?  Leave blank if N/A)
	- Type (If creating a ticket what type is it, "Day" or "GTCl"?  Leave blank if N/A)
	- Stock Symbol (Stock symbol being traded.  Leave blank if N/A)
	- Shares (If creating a ticket, how many shares do you want to trade?  Leave blank if N/A)

	Methods:
	- create - create a buy/sell ticket (returns a ticket ID/Key)
	- status - get the current status of a submitted ticket
	- cancel - cancel a ticket
	- close - mark a ticket as closed (this does not interact with the API, it simply changes the ticket status to "closed" on the new system)

	Examples:
	- create|C95E05F35290EE29C0A80132|||1-1|buy|Day|AAPL|50
	- create||jeffsaunders|JMF|1-1|sell|Day|AAPL|50
	- status|70443CA1391E026FC0A8015C
	- cancel|70443CA1391E026FC0A8015C
	- close|70443CA1391E026FC0A8015C

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

	case "create":
		$memberID	= substr(trim($aInput[4]), 0, strpos(trim($aInput[4]),"-"));
		$fundKey	= trim($aInput[1]);
		$login		= trim($aInput[2]);
		$fundSymbol	= trim($aInput[3]);
		$fundID		= trim($aInput[4]);
		$action		= trim($aInput[5]);
		$type		= trim($aInput[6]);
		$stockSymbol= trim($aInput[7]);
		$shares		= trim($aInput[8]);
		$reasons	= trim($aInput[9]);
		$description= trim($aInput[10]);
		$cgiScript	= "ecn.cgi";
		if ($fundKey != ""){
			$xmlString	= "<ecn><method>".$method."</method><fundKey>".$fundKey."</fundKey><stockSymbol>".$stockSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares></ecn>";
		}else{
			$xmlString	= "<ecn><method>".$method."</method><login>".$login."</login><fundSymbol>".$fundSymbol."</fundSymbol><stockSymbol>".$stockSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares></ecn>";
		}
		break;

	case "status":
		$ticketKey	= trim($aInput[1]);
		$cgiScript	= "ecn.cgi";
		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";
		break;

	case "cancel":
		$ticketKey	= trim($aInput[1]);
		$cgiScript	= "ecn.cgi";
		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";
		break;

	case "close":
		$ticketKey	= trim($aInput[1]);
		// No API call so skip all that...
//		$cgiScript	= "ecn.cgi";
//		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";
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

	// Closing does not require an ECN call so just bail
	if ($method == "close"){
		break;
	}

	// create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, $url);

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
print_r($aValues);

	//if we don't get "unresponsive" then bail out of the loop and process the response
	if ($aValues[0]['value'] != "unresponsive"){
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

// Check for errors (including 6th consecutive "unresponsive")
if (strpos($output, "<H1>Forbidden</H1>")){
	//Write error message to system_fetch_errors
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
}else if($aValues[0]['tag'] == "ERROR"){
	//Write error message to system_fetch_errors
	$query =	"INSERT INTO ".$fetch_errors_table." (
					timestamp,
					input,
					query,
					error
				) VALUES (
					UNIX_TIMESTAMP(),
					'".addslashes($input)."',
					'".addslashes($url)."',
					'".addslashes($aValues[0]['value'])."'
				)";
	$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	echo "Aborted - Error logged";
}else{ // No errors, process the response
	// Process the response depending on the method specified
	switch ($method){

		case "create":
			$ticketKey = $aValues[1]['value']; // returns the unique key assigned to the new ticket
//echo $ticketKey."\r\n";
			// Now insert the new ticket
			$query =	"INSERT INTO ".$fund_tickets_table." (
							member_id,
							fund_id,
							openned,
							action,
							type,
							symbol,
							shares,
							ticket_key,
							status,
							reasons,
							description
						) VALUES (
							'".trim($memberID)."',
							'".trim($fundID)."',
							UNIX_TIMESTAMP(),
							'".trim($action)."',
							'".trim($type)."',
							'".trim($stockSymbol)."',
							'".trim($shares)."',
							'".trim($ticketKey)."',
							'pending',
							'".str_replace('~', '|', trim($reasons))."',
							'".trim($description)."'
						)";
//echo $query."\r\n";
			$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;

		case "status":
			$ticketKey	= trim($aValues[1]['value']);
			$opened		= trim($aValues[2]['value']);
			$status		= trim($aValues[3]['value']);
			$closed		= trim($aValues[4]['value']);
			$net		= trim($aValues[5]['value']);
			$commission	= trim($aValues[6]['value']);
			$price		= trim($aValues[7]['value']);
			$secFee		= trim($aValues[8]['value']);
			$comment	= trim($aValues[9]['value']);

			// Now update the ticket as closed, if it is.
			if ($status == "closed"){
				$query =	"UPDATE ".$fund_tickets_table."
							 SET	status		= '".$status."',
									closed		= ".strtotime($closed).",
									net			= ".$net.",
									commission	= ".$commission.",
									price		= ".$price.",
									secFee		= ".$secFee.",
									comment		= '".$comment."'
							 WHERE	ticket_key	= '".$ticketKey."'
							";
//echo $query."\r\n";
				$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;

		case "cancel":
			$cancelled = $aValues[1]['value']; // returns "success" is successfully cancelled, "fail" if already closed/cancelled
			$ticketKey = $aValues[2]['value'];
//echo $cancelled."\r\n";
			// Now update the ticket as cancelled, if it is.
			if ($cancelled == "success"){
				$query =	"UPDATE ".$fund_tickets_table."
							 SET	status		= 'cancelled',
									closed		= UNIX_TIMESTAMP()
							 WHERE	ticket_key	= '".$ticketKey."'
							";
//echo $query."\r\n";
				$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			}
			echo "Success";
			break;

		case "close":
			// Just close it.  Similar to cancelling, but flagging it with a status of "closed" in case the actual closing was missed.
			$query =	"UPDATE ".$fund_tickets_table."
						 SET	status		= 'closed',
								closed		= UNIX_TIMESTAMP()
						 WHERE	ticket_key	= '".$ticketKey."'
						";
echo $query."\r\n";
			$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			echo "Success";
			break;

	}
}

?>