<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on ports 53100 - 53119
Once connected to a port simply send it a pipe (|) delimited string of values to pass to an XML request via the Xserve web server.

The string must be pipe (|) delimited with each element providing the following, in order:

	- Method (This tells the XML driven script which method to execute)
	- Key (This is the key relevant to the method - the Fund key if creating a ticket, the Ticket key if checking status, etc.  Leave blank if N/A)
	- Login (Member's Portfolio login name.  Leave blank if N/A)
	- Fund Symbol (Member's portfolio symbol,  Leave blank if N/A)
	- Fund ID (the fund ID from the new system.  Leave blank if N/A)
	- Action (If creating a ticket what do you want to do, "buy" or "sell"?  Leave blank if N/A)
	- Type (If creating a ticket what type is it, "Day" or "GTC"?  Leave blank if N/A)
	- Stock Symbol (Stock symbol being traded.  Leave blank if N/A)
	- Shares (If creating a ticket, how many shares do you want to trade?  Leave blank if N/A)
	- Limit (the limit price for GTC orders.  Leave blank if Day order)
	- Reason(s) (If creating a ticket, the reason(s) the trade is being made, if any, "~" delimited.  Leave blank if N/A)
	- Description (The description for this ticket.  Leave blank if N/A)
	- Resubmittal (Programmatically set flag to indicate if the request was resubmitted

	Methods:
	- create - create a buy/sell ticket (returns a ticket ID/Key)
	- status - get the current status of a submitted ticket
	- cancel - cancel a ticket
	- close - mark a ticket as closed (this does not interact with the API, it simply changes the ticket status to "closed" on the new system)

	Examples:
	- create|C95E05F35290EE29C0A80132|||1-1|buy|Day|AAPL|50||Because Apple will never go down in value~I'm an Apple fanboy|Some fruit company (NOT USED)
	- create||jeffsaunders|JMF|1-1|sell|Day|AAPL|50||Because Apple will never go down in value~I'm an Apple fanboy|Some fruit company|0
	- create||jeffsaunders|JMF|1-1|sell|GTC|AAPL|50|100.00 (minimum for a successful ticket)||0
	- status|70443CA1391E026FC0A8015C
	- cancel|70443CA1391E026FC0A8015C
	- close|70443CA1391E026FC0A8015C

*/

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define API name and queue directory
$api = "API2";
$api_dir = "/api2";

// Set up listener
$handle = fopen('php://stdin','r');

// Listen
$input = fgets($handle, 1024);

// Stop listening
fclose($handle);

// Parse input
$aInput = explode("|", $input);
print_r($aInput); // Debug

// Assign passed method value
$method	= trim($aInput[0]);

// function to generate (and write) transaction
function gen_transaction($table, $link, $sInput){
	$query = "
		INSERT INTO ".$table." (
			timestamp,
			input
		) VALUES (
			UNIX_TIMESTAMP(),
			'".addslashes(trim($sInput))."'
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	return $mLink->lastInsertId();
}

// function to update transaction with the XML string submitted
function update_transaction($table, $link, $sXML, $nID, $sError=NULL){
	if ($sError == NULL){
		$query = "
			UPDATE ".$table."
			SET	xml	= '".$sXML."'
			WHERE trans_id = ".$nID."
	 	";
	}else{
		$query = "
			UPDATE ".$table."
			SET	xml	= '".$sXML."',
				error = '".$sError."'
			WHERE	trans_id = ".$nID."
	 	";
	}
	try{
		$rsUpdate = $mLink->prepare($query);
		$rsUpdate->execute();
	}
	catch(PDOException $error){
		// Log any error
 		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	return true;
}

// Build XML query string and assign proper CGI script for the passed method
switch ($method){

	case "create":
		// Generate a transaction ID and log it
		$trans_key	= gen_transaction($transactions_api_table, $linkID, $input);

		// Assign parsed values
		$memberID	= substr(trim($aInput[4]), 0, strpos(trim($aInput[4]),"-"));  // Glean from Fund ID
		$fundKey	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundSymbol	= rawurlencode(trim($aInput[3]));
		$fundID		= trim($aInput[4]);
		$action		= trim($aInput[5]);
//		$type		= trim($aInput[6]);
		$type		= (trim($aInput[6]) == "" ? "GTC" : trim($aInput[6]));  // Default to GTC - added to cover for instances where the type was not being defined
		$stockSymbol= trim($aInput[7]);
		$shares		= trim($aInput[8]);
		$limit		= trim($aInput[9]);
		$reasons	= rawurlencode(trim($aInput[10]));
		$description= rawurlencode(trim($aInput[11]));
		$resubmitted= (trim($aInput[12]) == "1" ? trim($aInput[12]) : "0");
		$comment	= $reasons.($reasons != "" ? "|" : "").$description;
//		$ticketID	= time().rand(); // Unix time plus a random number
		$ticketID	= $trans_key;
		$process	= "ecn";

		// Turn stock symbol slashes into dots for FrontBase
		$fbSymbol	= str_replace("/", ".", $stockSymbol);

		// Build XML string
		if ($fundKey != ""){  // RARELY, if ever, used
			$xmlString	= "<ecn><method>".$method."</method><ticketID>".$ticketID."</ticketID><fundKey>".$fundKey."</fundKey><stockSymbol>".$fbSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares><limit>".$limit."</limit><comment>".$comment."</comment></ecn>";
		}else{
			$xmlString	= "<ecn><method>".$method."</method><ticketID>".$ticketID."</ticketID><login>".$login."</login><fundSymbol>".$fundSymbol."</fundSymbol><stockSymbol>".$fbSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares><limit>".$limit."</limit><comment>".$comment."</comment></ecn>";
		}

		// Update the transaction record with the XML string; Log error if shares value not numeric (usually "NaN")
		if (!is_numeric($shares)){

			$errorString = "Non-numeric share quantity";
			update_transaction($transactions_api_table, $linkID, $xmlString, $trans_key, $errorString);

			//Write error message to system_fetch_errors
			$query = "
				INSERT INTO ".$fetch_errors_table." (
					timestamp,
					server,
					input,
					error
				) VALUES (
					UNIX_TIMESTAMP(),
					'".$api."',
					'".addslashes($input)."',
					'".$errorString."'
				)
			";
			try{
				$rsInsert = $mLink->prepare($query);
				$rsInsert->execute();
			}
			catch(PDOException $error){
				// Log any error
	  			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

		}else{

			update_transaction($transactions_api_table, $linkID, $xmlString, $trans_key);

		}

		// Get the current price of the stock being traded
		$query = "
			SELECT Last
			FROM stock_feed
			WHERE Symbol = '".$stockSymbol."'
		";
		try {
			$rsStockPrice = $mLink->prepare($query);
			$rsStockPrice->execute();
		}
		catch(PDOException $error){
			// Log any error
   			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$stockPrice= $rsStockPrice->fetch(PDO::FETCH_ASSOC)
		$quotePrice = $stockPrice["Last"];
		$limitPrice = ($limit == "" ? 0 : $limit);
		$status		= (isset($errorString) ? "failed" : "pending");

		// Create the ticket record
		$query = "
			INSERT INTO ".$fund_tickets_table." (
				ticket_id,
				member_id,
				fund_id,
				openned,
				action,
				type,
				symbol,
				shares,
				`limit`,
				quote_price,
				status,
				reasons,
				description,
				resubmitted
			) VALUES (
				'".trim($ticketID)."',
				'".trim($memberID)."',
				'".trim($fundID)."',
				UNIX_TIMESTAMP(),
				'".trim($action)."',
				'".trim($type)."',
				'".trim($stockSymbol)."',
				'".trim($shares)."',
				".$limitPrice.",
				".$quotePrice.",
				'".$status."',
				'".addslashes(str_replace('~', '|', trim($reasons)))."',
				'".addslashes(trim($description))."',
				".$resubmitted."
			)
		";
		try{
			$rsInsert = $mLink->prepare($query);
			$rsInsert->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		if (isset($errorString)){
			die();
		}
		break;

	case "status":
		$ticketKey	= trim($aInput[1]);
		$process	= "ecn";
		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";
		break;

	case "cancel":
		$ticketKey	= trim($aInput[1]);
		$process	= "ecn";
		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";
		break;

	case "close":
		$ticketKey	= trim($aInput[1]);
		// No API call so skip all that...
//		$process	= "ecn";
//		$xmlString	= "<ecn><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey></ecn>";

		// ...and just close it.  Similar to cancelling, but flagging it with a status of "closed" in case the actual closing was missed.
		$query = "
			UPDATE ".$fund_tickets_table."
			SET	status 	= 'closed',
				closed 	= UNIX_TIMESTAMP()
			WHERE	ticket_key	= '".$ticketKey."'
		";
		try{
			$rsUpdate = $mLink->prepare($query);
			$rsUpdate->execute();
		}
		catch(PDOException $error){
			// Log any error
	 		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		//echo "Success";

		// Just bail, there's nothing else to do
		die();

	// No valid method passed, log it as an error
	default:
		//Write error message to system_fetch_errors
		$query = "
			INSERT INTO ".$fetch_errors_table." (
				timestamp,
				server,
				input,
				error
			) VALUES (
				UNIX_TIMESTAMP(),
				'".$api."',
				'".addslashes($input)."',
				'Invalid Method Specified on Input'
			)
		";
		try{
			$rsInsert = $mLink->prepare($query);
			$rsInsert->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		//echo "Aborted - Error logged\n\n";
		die();

}
echo $xmlString."\n\n";  // Debug

// If there is a $transID and it's NOT 0, use it for the trailing number
if (isset($transID) && $transID !== "0"){
	$trailingNumber = $transID;
}else{
	// Generate a unique number to tack on the end of the filename (to make it unique)
	$trailingNumber = rand(0, 65535);
}

// Set some ground rules
ob_implicit_flush();

// Create a unique file (i.e. ecn_input_1409077226_825)
$fp = fopen($api_dir."/".$process."_processing/".$process."_input_".time()."_".$trailingNumber, "w");

// Write the passed query string to the file
fwrite($fp, $xmlString);

// Close 'er up
fclose($fp);

// Temporarily test to see if the method just run was one with a transID - log if it was
if (isset($transID) && $transID !== "0"){

	// Log transaction submission in log_transactions_api table
	$query = "
		UPDATE ".$legacy_api_trans_table."
		SET	processing			= 1,
			xml_sent			= '".$xmlString."',
			xml_sent_timestamp	= UNIX_TIMESTAMP()
		WHERE trans_id	= ".$transID."
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

// Explicitely close the PDO connection
$mLink = null;

?>