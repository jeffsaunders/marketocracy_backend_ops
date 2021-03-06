<?php
/*
This process runs as a server daemon, controlled by xinetd, listening for connections on ports 52100 - 52499 (API2)
Once connected to a port simply send it a pipe (|) delimited string of values to pass to an XML request via the Xserve web server.

/// Membership
	- newManager - Create a new member in the old system (Do this when a new member joins)
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)
		- Email (Member's email address)
		- Member ID (Member's ID from the new system)

		Example:
		- newManager|0|jeffsaunders|jeff.saunders@marketocracy.com|1
<manageradmin><method>newManager</method><login>foofoofoo</login><email>foofoofoo%40foo.com</email><member_ID>99999</member_ID><trans_ID>0</trans_ID></manageradmin>
	- newFund - Create a new fund
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)
		- Fund Type ("long" or "short")
		- Fund Name (The name to call it - must be unique for member but not across members)
		- Fund Symbol (3 or 4 character "ticker" symbol - must be unique for member but not across members)
		- Fund ID (The Fund ID assigned by the new system)

		Example:
		- newFund|0|jeffsaunders|long|New Fund|NEW1|1-1

	- deactivateFund - Deactivate an existing fund
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)
		- Fund Symbol (3 or 4 character "ticker" symbol - must be unique for member but not across members)
		- Fund ID (The Fund ID assigned by the new system)

		Example:
		- deactivateFund|0|jeffsaunders|NEW1|1-3

	- updateSymbol - Change a fund's symbol
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)
		- Fund ID (the fund ID from the new system)
		- Old/Current Fund Symbol (Current "ticker" symbol)
		- New Fund Symbol ("ticker" symbol to change to - must be unique for member but not across members)

		Example:
		- updateSymbol|0|jeffsaunders|1-2|JSF|NEW1

	- updateName - Change a fund's name
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)
		- Fund ID (the fund ID from the new system)
		- Fund Symbol (the fund's "ticker" symbol)
		- New Fund Name (The new name for the fund)

		Example:
		- updateName|0|jeffsaunders|1-2|JSF|My Short Fund

	- managerPassword - Get a members password (in the clear)
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Login (Member's Portfolio login name)

		Example:
		- managerPassword|0|jeffsaunders

	- importPassword - Obtain and encrypt an imported member's password (build auth record)
		(This is not passed on to the API but instead spawns a local script which performs this)
		- Method (This tells this process to spawn the external script)
		- Login (Member's Portfolio login name)
		- Password (Member's password)
		- Email (Member's email address)
		- Member ID (Member's ID from the new system)

		Example:
		- importPassword|0|jeffsaunders|n0thing!|jeff.saunders@marketocracy.com|1


/// Fund Data
    The string must be comma delimited with each element providing the following, in order:
	- Method (This tells the XML driven script which method to execute)
	- Trans ID (This is assigned programmatically to every transaction for tracking)
	- Login (Member's Portfolio login name)
	- Fund ID (the fund ID from the new system)
	- Symbol (Member's portfolio symbol)
	- Start Date (The date whose info you want, start date if it's a range, in YYYYMMDD format)
	- End Date (The end date if for a range, leave blank if only 1 day)
	- Active Only (Return only active positions, if applicable)

	Methods:
	- maxDate - get the last date the fundPrice server has data for
	- priceManager - starts the fund pricing process (only returns "sent", no completion notification)
	- livePrice - Live pricing for a given fund, to the minute
	- priceRun - get fund pricing information for a day or range of days (specify an end date to invoke range)
	- aggregateStatistics - get a fund's aggregate statistics for a given date
	- alphaBetaStatistics - get a fund's alphabeta statistics for a given date
	- positionDetail - get a fund's content details (stocks held) for a given date
	- positionInfo - get a specific stock within a fund's details, as of last closing date
	- allPositionInfo - get info for all positions in a fund, as of last closing date
	- tradesForPosition - get trade details for a stock, per fund, since date specified (or inception if left blank)
	- tradesForFund - get trade details for all stocks in a fund since date specified (or inception if left blank)
	- untrade - untrade a ticket (pass it the ticketKey)
//	- positionStratification - get basic stratification details for the stocks in a given fund
//	- stylePositionStratification - get stratification details for the stocks in a given fund, by style
//	- sectorPositionStratification - get stratification details for the stocks in a given fund, by sector

	Examples:
	- maxDate
	- priceManager|0|jeffsaunders
	- livePrice|0|jeffsaunders|1-1|JMF
	- priceRun|0|jeffsaunders|1-1|JMF|20140601
	- priceRun|0|jeffsaunders|1-1|JMF|20140530|20140601 (gets 3 day's worth)
	- aggregateStatistics|0|jeffsaunders|1-1|JMF|20140601
	- alphaBetaStatistics|0|jeffsaunders|1-1|JMF|20140601
	- positionDetail|0|jeffsaunders|1-1|JMF|20140601
	- positionInfo|0|jeffsaunders|1-1|JMF|AAPL
	- allPositionInfo|0|jeffsaunders|1-1|JMF or |1 (Active Positions Only)
	- allPositionInfo|0|jeffsaunders|1-1|JMF|0 (All Positions)
	- tradesForPosition|0|jeffsaunders|1-1|JMF|AAPL|20140101
	- tradesForFund|0|jeffsaunders|1-1|JMF|20140101
	- untrade|0|F8DB1C6D54F0B0C2C0A86F98
//	- positionStratification|0|jeffsaunders|1-1|JMF
//	- positionStratification|0|jeffsaunders|1-1|JMF|1 (Active Positions Only)
//	- stylePositionStratification|0|jeffsaunders|1-1|JMF
//	- stylePositionStratification|0|jeffsaunders|1-1|JMF|1 (Active Positions Only)
//	- sectorPositionStratification|0|jeffsaunders|1-1|JMF
//	- sectorPositionStratification|0|jeffsaunders|1-1|JMF|1 (Active Positions Only)

	Special versions for populating statistical history tables (Add a capital H to the fundID):
	- aggregateStatistics|0|jeffsaunders|1-1H|JMF|20141231 (get values for last day of the year)
	- alphaBetaStatistics|0|jeffsaunders|1-1H|JMF|20141231


/// Stock Data
    The string must be comma delimited with each element providing the following, in order:
		- Method (This tells the XML driven script which method to execute)
		- Trans ID (This is assigned programmatically to every transaction for tracking)
		- Symbol (The "ticker" symbol for the stock)

	Methods:
	- stockInfo - get's current stock price info, including feed data
	- stockActions - Get all Corporate actions for a particular stock

	Examples:
	- stockInfo|0|AAPL
	- stockActions|0|AAPL


/// Rankings Data
    The string must be comma delimited with each element providing the following, in order:
	- Method (This tells the XML driven script which method to execute)
	- Trans ID (This is assigned programmatically to every transaction for tracking)
	- Login (Member's Portfolio login name)
	- Fund ID (the fund ID from the new system)
	- Symbol (Member's portfolio symbol)

	Methods:
	- quarterlyRanksForFund - get the quarterly rankings for the passed fund

	Examples:
	- quarterlyRanksForFund|0|jeffsaunders|1-1|JMF


/// CA Data
    The string must be comma delimited with each element providing the following, in order:
	- Method (This tells the XML driven script which method to execute)
	- Trans ID (This is assigned programmatically to every transaction for tracking)
	- Date (Date requested - will return all CAs of type defined by <method> for this date)

	Methods:
	- cashDividendsOnDate
	- stockDividendsOnDate
	- splitsOnDate
	- spinoffsOnDate
	- symbolChangesOnDate
	- cusipChangesOnDate
	- acquisitionsOnDate
	- bankruptciesOnDate
	- listingsOnDate
	- delistingsOnDate

	Example:
	- cashDividendsOnDate|0|20151109

*/

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define API queue directory
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

// Build XML query string and assign proper CGI script for the passed method
switch ($method){

// Membership methods
	case "newManager":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$email		= rawurlencode(trim($aInput[3]));
		$memberID	= trim($aInput[4]);
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><email>".$email."</email><member_ID>".$memberID."</member_ID><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "newFund":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$type		= trim($aInput[3]);
		$name		= rawurlencode(trim($aInput[4]));
		$symbol		= rawurlencode(trim($aInput[5]));
		$fundID		= trim($aInput[6]);
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><longOrShort>".$type."</longOrShort><name>".$name."</name><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "deactivateFund":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$symbol		= rawurlencode(trim($aInput[3]));
		$fundID		= trim($aInput[4]);
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "updateSymbol":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$oldSymbol	= rawurlencode(trim($aInput[4]));
		$newSymbol	= rawurlencode(trim($aInput[5]));
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><fundSymbol>".$oldSymbol."</fundSymbol><newSymbol>".$newSymbol."</newSymbol><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "updateName":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$newName	= rawurlencode(trim($aInput[5]));
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><newName>".$newName."</newName><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "managerPassword":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$process	= "manageradmin";
		$xmlString	= "<manageradmin><method>".$method."</method><login>".$login."</login><trans_ID>".$transID."</trans_ID></manageradmin>";
		break;

	case "importPassword":
		$login		= addslashes(trim($aInput[1]));
		$password	= addslashes(trim($aInput[2]));
		$email		= addslashes(trim($aInput[3]));
		$memberID	= trim($aInput[4]);
		// This process simply spawns an external script - no API call is actually made
		$cmd 		= '/usr/bin/php /var/www/html/batch/importAuth.php "username='.$login.'&password='.$password.'&email='.$email.'&member_id='.$memberID.'" > /dev/null &';
		//echo $cmd."\n\n";
		exec($cmd);
		die();

// Fund Data methods
	case "maxDate":
		$transID	= trim($aInput[1]);
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "priceManager":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "livePrice":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$type		= ($end == "" ? "day" : "range");
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "priceRun":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$start		= trim($aInput[5]);
		$end		= trim($aInput[6]);
		$type		= ($end == "" ? "day" : "range");
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><startDate>".$start."</startDate><endDate>".($type == "day" ? $start : $end)."</endDate><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "aggregateStatistics":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$start		= trim($aInput[5]);
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "alphaBetaStatistics":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$start		= trim($aInput[5]);
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date><trans_ID>".$transID."</trans_ID></fundPrice>";
		break;

	case "periodPrices":
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$aDates		= explode(",", trim($aInput[5]));
		$process	= "fundprice";
		$xmlString	= "<fundPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID><dates>";
		foreach ($aDates as $date){
			$xmlString .= "<date>".$date."</date>";
		}
		$xmlString .= "</dates></fundPrice>";
		break;


// Stock Data methods
	case "positionInfo":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		$stock		= trim($aInput[4]);
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$stock."</stockSymbol><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></stockPrice>";
		break;

	case "allPositionInfo":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		// Set default to only return active positions
		$activeOnly = (isset($aInput[4]) ? trim($aInput[4]) : "1");
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><activeOnly>".$activeOnly."</activeOnly></stockPrice>";
		break;

/* No longer used
	case "positionStratification":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		// Set default to only return active positions
		$activeOnly = (isset($aInput[4]) ? trim($aInput[4]) : "1");
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><activeOnly>".$activeOnly."</activeOnly></stockPrice>";
		break;

	case "stylePositionStratification":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		// Set default to only return active positions
		$activeOnly = (isset($aInput[4]) ? trim($aInput[4]) : "1");
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><activeOnly>".$activeOnly."</activeOnly></stockPrice>";
		break;

	case "sectorPositionStratification":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		// Set default to only return active positions
		$activeOnly = (isset($aInput[4]) ? trim($aInput[4]) : "1");
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><activeOnly>".$activeOnly."</activeOnly></stockPrice>";
		break;
*/
	case "stockInfo":
		// Assign needed passed values
		$symbol		= trim($aInput[1]);
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$symbol."</stockSymbol></stockPrice>";
		break;

	case "stockActions":
		// Assign needed passed values
		$symbol		= trim($aInput[1]);
		$process	= "stockprice";
		$xmlString	= "<stockPrice><method>".$method."</method><stockSymbol>".$symbol."</stockSymbol></stockPrice>";
		break;


// Trade Data methods
	case "positionDetail":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$start		= trim($aInput[5]);
		$process	= "trade";
		$xmlString	= "<trade><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID><date>".$start."</date><trans_ID>".$transID."</trans_ID></trade>";
		break;

	case "tradesForPosition":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
		$symbol		= rawurlencode(trim($aInput[4]));
		$stock		= trim($aInput[5]);
		$start		= trim($aInput[6]);
		// Set date to "before time" if blank, to get ALL
		if ($start == ""){
			$start = "20000101";
		}
		$process	= "trade";
		$xmlString	= "<trade><method>".$method."</method><stockSymbol>".$stock."</stockSymbol><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><startDate>".$start."</startDate><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></trade>";
		break;

	case "tradesForFund":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$login		= rawurlencode(trim($aInput[2]));
		$fundID		= trim($aInput[3]);
 		$symbol		= rawurlencode(trim($aInput[4]));
		$start		= trim($aInput[5]);

		// Set date to "before time" if blank, to get ALL
		if ($start == ""){
			$start = "20000101";
		}
		$process	= "trade";
		$xmlString	= "<trade><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><startDate>".$start."</startDate><fund_ID>".$fundID."</fund_ID><trans_ID>".$transID."</trans_ID></trade>";
		break;

	case "untrade":
		$transID	= trim($aInput[1]);
		$ticketKey	= trim($aInput[2]);
		$process	= "trade";
		$xmlString	= "<trade><method>".$method."</method><ticketKey>".$ticketKey."</ticketKey><trans_ID>".$transID."</trans_ID></trade>";
		break;


// Ranking Data methods
	case "quarterlyRanksForFund":
		// Assign needed passed values
		$login		= rawurlencode(trim($aInput[1]));
		$fundID		= trim($aInput[2]);
		$symbol		= rawurlencode(trim($aInput[3]));
		$process	= "ranking";
		$xmlString	= "<ranking><method>".$method."</method><login>".$login."</login><fundSymbol>".$symbol."</fundSymbol><fund_ID>".$fundID."</fund_ID></ranking>";
		break;

	case "rankDates":
		// Assign needed passed values
		$process	= "ranking";
		$xmlString	= "<ranking><method>".$method."</method></ranking>";
		break;


// CA Data methods
	case "cashDividendsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "stockDividendsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "splitsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "spinoffsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "symbolChangesOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "cusipChangesOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "acquisitionsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "bankruptciesOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "listingsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;

	case "delistingsOnDate":
		// Assign needed passed values
		$transID	= trim($aInput[1]);
		$date		= trim($aInput[2]);
		$process	= "ca";
		$xmlString	= "<ca><method>".$method."</method><date>".$date."</date><trans_ID>".$transID."</trans_ID></ca>";
		break;


	// No valid method passed, log it as an error
	default:

		//Write error message to system_fetch_errors
		$query =
			"INSERT INTO ".$fetch_errors_table." (
			timestamp,
			server,
			input,
			error
		) VALUES (
			UNIX_TIMESTAMP(),
			'API2',
			'".addslashes($input)."',
			'Invalid Method Specified on Input'
		)";
		try{
			$rsInsert = $mLink->prepare($query);
			$rsInsert->execute();
		}
		catch(PDOException $error){
			// Log any error
   			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}



		echo "Aborted - Error logged\n\n";
		die();
}

echo $xmlString."\n\n"; // Debug

// Generate a unique number to tack on the end of the filename (to make it unique)
$trailingNumber = rand(0, 65535);

// Temporarily test to see if the method just run was one with a transID - log if it was
if (isset($transID)){
	// Log transaction submission in log_transactions_api table
	$query = "	UPDATE ".$legacy_api_trans_table."
				SET	processing			= 1,
					xml_sent			= '".$xmlString."',
					xml_sent_timestamp	= UNIX_TIMESTAMP()
				WHERE trans_id	= ".$transID."
	";
//	$rs_update = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}


	// If there is a $transID and it's NOT 0, use it for the trailing number instead
	if ($transID !== "0"){
		$trailingNumber = $transID;
	}
}

// Make sure the NFS mount is up, mount it if it's not
//if (!file_exists("/mnt/api/".$process."_processing")){
//	exec("mount -a");
//	sleep(1);
//}

// Set some ground rules
ob_implicit_flush();

// Create a unique file (i.e. fundprice_input_1409077226_825)
//$fp = fopen($api_dir."/".$process."_processing/".$process."_input_".time()."_".rand(0, 65535), "w");
$fp = fopen($api_dir."/".$process."_processing/".$process."_input_".time()."_".$trailingNumber, "w");

// Write the query string to the file
fwrite($fp, $xmlString);

// Close 'er up
fclose($fp);
//mysql_close($linkID);

?>