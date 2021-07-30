<?php
// This commandline batch script generates API queries to pull in the MISSING positions for each specified fund on the first day of each month for the past 5 years.
// This is really a "one-shot" process but the code may be adaptable for future needs
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* JUST HARD CODE THESE VALUES
// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Test passed values
if (!isset($_REQUEST['input'])){
	echo "You must specify an input file (e.g. SACReport.php \"input=filename.csv\")\n";
	die();
}

if (!isset($_REQUEST['fundname'])){
	echo "You must specify a Hedge Fund name file (e.g. SACReport.php \"fundname=SAC\")\n";
	die();
}

if (!file_exists($_REQUEST['input'])){
	echo "Input file does not exist\n";
	die();
}

// NEED TO SANITIZE PASSED DATE VALUE TO ASSURE VALIDITY HERE........
*/

// Start me up
session_start();

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Load mailer
//require('/var/www/html/includes/PHPMailer/class.phpmailer.php');


if(!function_exists("array_column")){

	function array_column($array,$column_name){
		return array_map(function($element) use($column_name){return $element[$column_name];}, $array);
	}
}





// Assign passed values
$timestamp = 1325419200; // 01/01/2012

// Set some values
$api = "API2";
if ($api == "API1"){
	$startPort = 52000;
	$endPort = 52099;
}else{
	$startPort = 52100;
	$endPort = 52499;
}

$port = $startPort;

// Open the input CSV file and read it in line by line
$fileName = "/var/www/html/tmp/SAC_Funds_20160927.csv";
$input = fopen($fileName, "r");
// Step through the passed fund keys
while (!feof($input)) {

	$fundkey = fgets($input);
//$fundkey = "c95e05f35290ee29c0a80132"; //Testing
//$fundkey = "FFFF195C50D9431DC0A80132";
//$fundkey = "5ADB06433AB3B411C0A801E0";

echo $fundkey."\n";

	// Get the API query information
	$query = "
	    SELECT f.fb_primarykey as fundkey, f.fund_symbol, f.fund_id, m.username
	    FROM members_fund f, members m
	    WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
		AND m.member_id = f.member_id
	";
//WHERE f.fb_primarykey = :fundKey
//WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
//AND p.date = :reportDate

	try{
		$rsAPI = $mLink->prepare($query);
		$rsAPI->execute(); // Sans variable bounding
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	while($APIdata = $rsAPI->fetch(PDO::FETCH_ASSOC)){

		// Get all non-missing positions
		$query = "
			SELECT DISTINCT p.date
			FROM members_fund_positions p, members_fund f
			WHERE f.fb_primarykey = \"X'".trim($fundkey)."'\"
			AND p.fund_id = f.fund_id
			AND p.unix_date >= ".$timestamp."
			AND DAYOFMONTH(FROM_UNIXTIME(p.unix_date)) = 1
			ORDER BY date ASC
		";
		try{
			$rsPositions = $mLink->prepare($query);
			$rsPositions->execute(); // Sans variable bounding
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

//		$aPositions = $rsPositions->fetch(PDO::FETCH_ASSOC);
		$aPositions = $rsPositions->fetchAll(PDO::FETCH_ASSOC);

//print_r($aPositions);
		$targetDate = 20120101;

		while ($targetDate <= 20161201){
			if(!array_search($targetDate, array_column($aPositions, "date"))){
echo $targetDate."\n";
				// Build API query
				$query = "positionDetail|0|".$APIdata['username']."|".$APIdata['fund_id']."|".$APIdata['fund_symbol']."|".$targetDate;
//positionDetail|0|jeffsaunders|1-1|JMF|20140601
				//echo $query."\r\n";

				// Set the port number for the API call
				if ($port == $endPort){
					$port = $startPort;
				}else{
					$port++;
				}

			// Execute the query call (call myself on another port)
			exec('/var/www/html/batch/process-ecn-query.sh "'.$port.'" "'.$query.'" > /dev/null &');


			}
			$targetDate = date("Ymd", strtotime("+1 month", mktime(0, 0, 0, substr($targetDate, 4, 2), substr($targetDate, 6, 2), substr($targetDate, 0, 4))));
		}

//die();

/*
		while ($timestamp <= time()){

			$date = date("Ymd", $timestamp);

//echo $date."\n";
//die();

			$query = "positionDetail|0|".$APIdata['username']."|".$APIdata['fund_id']."|".$APIdata['fund_symbol']."|".$date;
//positionDetail|0|jeffsaunders|1-1|JMF|20140601
			//echo $query."\r\n";

			// Set the port number for the API call
			if ($port == $endPort){
				$port = $startPort;
			}else{
				$port++;
			}

			// Execute the query call (call myself on another port)
//			exec('/var/www/html/batch/process-ecn-query.sh "'.$port.'" "'.$query.'" > /dev/null &');

			$timestamp = strtotime("+1 month", $timestamp);

		}
		$timestamp = 1325419200; // 01/01/2012
//echo "\n";
		sleep(1);
//die();
*/
	}

//if ($fundkey == "00494AAE43F39EF5C0A80134"){
//	die();
//}

}

// Go get the needed info for each fund key
// Set the $target_date to the $start_date
// Loop until the $target_date > today
	// build API query string and call the include to submit it
	// make $target_date the first day of the next month
// End Loop, go get the next fund key, rinse repeat


/*
positionDetail|0|jeffsaunders|1-1|JMF|20140601

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
*/



?>
