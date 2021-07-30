<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on ports 53000 - 53019
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

	Methods:
	- create - create a buy/sell ticket (returns a ticket ID/Key)
	- status - get the current status of a submitted ticket
	- cancel - cancel a ticket
	- close - mark a ticket as closed (this does not interact with the API, it simply changes the ticket status to "closed" on the new system)

	Examples:
	- create|C95E05F35290EE29C0A80132|||1-1|buy|Day|AAPL|50||Because Apple will never go down in value~I'm an Apple fanboy|Some fruit company (NOT USED)
	- create||jeffsaunders|JMF|1-1|sell|Day|AAPL|50||Because Apple will never go down in value~I'm an Apple fanboy|Some fruit company
	- create||jeffsaunders|JMF|1-1|sell|GTC|AAPL|50|100.00 (minimum for a successful ticket)
	- status|70443CA1391E026FC0A8015C
	- cancel|70443CA1391E026FC0A8015C
	- close|70443CA1391E026FC0A8015C
create||jeffsaunders|JMF|1-1|buy|Day|AAPL|1||Because Apple will never go down in value~I'm an Apple fanboy|Some fruit company|1
*/

// Set up listener
$handle = fopen('php://stdin','r');
$input = fgets($handle, 1024);
fclose($handle);

$aInput = explode("|", $input);
//print_r($aInput);

// Assign passed method value
$method	= trim($aInput[0]);

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfig.php");

// function to generate (and write) transaction
function gen_transaction($table, $link, $sInput){
	$query =	"INSERT INTO ".$table." (
					timestamp,
					input
				) VALUES (
					UNIX_TIMESTAMP(),
					'".addslashes(trim($sInput))."'
				)";
//echo $query."\r\n";
	$rs_insert = mysql_query($query, $link) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	return mysql_insert_id($link);
}

// function to update transaction with the XML string submitted
function update_transaction($table, $link, $sXML, $nID){
	$query =	"UPDATE ".$table."
				 SET	xml	= '".$sXML."'
				 WHERE	trans_id = ".$nID."
					";
//echo $query."\r\n";
	$rs_update = mysql_query($query, $link); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	return true;
}

// Build XML query string and assign proper CGI script for the passed method
switch ($method){

	case "create":
		// Generate a transaction ID and log it
		$trans_key	= gen_transaction($transactions_api_table, $linkID, $input);

		// Assign parsed values
		$memberID	= substr(trim($aInput[4]), 0, strpos(trim($aInput[4]),"-"));
		$fundKey	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundSymbol	= rawurlencode(trim($aInput[3]));
		$fundID		= trim($aInput[4]);
		$action		= trim($aInput[5]);
		$type		= trim($aInput[6]);
		$stockSymbol= trim($aInput[7]);
		$shares		= trim($aInput[8]);
		$limit		= trim($aInput[9]);
		$reasons	= rawurlencode(trim($aInput[10]));
		$description= rawurlencode(trim($aInput[11]));
		$resubmitted= trim($aInput[12]);
		$comment	= $reasons.($reasons != "" ? "|" : "").$description;
//		$ticketID	= time().rand(); // Unix time plus a random number
		$ticketID	= $trans_key;
//die($ticketID);
		$process	= "ecn";
		if ($fundKey != ""){
			$xmlString	= "<ecn><method>".$method."</method><ticketID>".$ticketID."</ticketID><fundKey>".$fundKey."</fundKey><stockSymbol>".$stockSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares><limit>".$limit."</limit><comment>".$comment."</comment></ecn>";
		}else{
			$xmlString	= "<ecn><method>".$method."</method><ticketID>".$ticketID."</ticketID><login>".$login."</login><fundSymbol>".$fundSymbol."</fundSymbol><stockSymbol>".$stockSymbol."</stockSymbol><buyOrSell>".$action."</buyOrSell><dayOrGTC>".$type."</dayOrGTC><shares>".$shares."</shares><limit>".$limit."</limit><comment>".$comment."</comment></ecn>";
		}

		// Update the transaction record with the XML string
		update_transaction($transactions_api_table, $linkID, $xmlString, $trans_key);

		// Get the current price of the stock being traded
		$query = "
			SELECT Last
			FROM stock_feed
			WHERE Symbol = '".$stockSymbol."'
		";
		$rs_stockPrice = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		$stockPrice = mysql_fetch_assoc($rs_stockPrice);
		$quotePrice = $stockPrice["Last"];
		$limitPrice = ($limit == "" ? 0 : $limit);

		// Create the ticket record
		$query =	"INSERT INTO ".$fund_tickets_table." (
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
						'pending',
						'".addslashes(str_replace('~', '|', trim($reasons)))."',
						'".addslashes(trim($description))."',
						".$resubmitted."
					)";
//echo $query."\r\n";
		$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
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
		$query =	"UPDATE ".$fund_tickets_table."
					 SET	status		= 'closed',
							closed		= UNIX_TIMESTAMP()
					 WHERE	ticket_key	= '".$ticketKey."'
					";
//echo $query."\r\n";
		$rs_update = mysql_query($query, $linkID); // or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		//echo "Success";

		// Just bail, there's nothing else to do
		die();

	// No valid method passed, log it as an error
	default:
		//Write error message to system_fetch_errors
		$query =	"INSERT INTO ".$fetch_errors_table." (
						timestamp,
						server,
						input,
						error
					) VALUES (
						UNIX_TIMESTAMP(),
						'API',
						'".addslashes($input)."',
						'Invalid Method Specified on Input'
					)";
		$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		//echo "Aborted - Error logged\n\n";
		die();

}
echo $xmlString."\r\n";

// Set some ground rules
ob_implicit_flush();

// Create a unique file (i.e. ecn_input_1409077226_825)
$fp = fopen("/api/".$process."_processing/".$process."_input_".time()."_".rand(0, 65535), "w");

// Write the passed query string to the file
fwrite($fp, $xmlString);

// Close 'er up
fclose($fp);


?>