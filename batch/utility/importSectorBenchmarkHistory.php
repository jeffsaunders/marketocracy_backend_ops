<?php
/*
The purpose of this script is to import the historical closing price history of the sector benchmark funds (SPDRs) from Xignite using JSON.
*Note - this will not run within a web browser.
*/
die("Execution Prevented\n");  // Stop accidental execution.
// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Define all the benchmark symbols
$aSymbols = ["XLE","XLB","XLI","XLY","XLP","XLV","XLF","XLU","XLK","XLRE","VOX"];

// Define the start and end dates - VERY IMPORTANT TO GET RIGHT!
//$startDate = "1/1/2000"; // mm/dd/yyyy format withOUT leading zeroes - that's how Xignite wants it
//$endDate = "6/13/2017";
$startDate = "6/14/2017"; // mm/dd/yyyy format withOUT leading zeroes - that's how Xignite wants it
$endDate = "6/14/2017";

// Define our Xignite account token
$token = "EF2662FA141B4DC086F6A72B2D15AD2C";

// Run for each defined symbol
foreach ($aSymbols as $symbol){

	// Get the requested data from Xignite (JSON)
	$url = "https://www.xignite.com/xGlobalHistorical.json/GetGlobalHistoricalQuotesRange?IdentifierType=Symbol&Identifier=".$symbol."&AdjustmentMethod=SplitAndProportionalCashDividend&StartDate=".$startDate."&EndDate=".$endDate."&_token=".$token;
	$json = file_get_contents($url);
	$data = json_decode($json);

//print_r($data);

	// Pull the pricing info for each day
	$aGlobalQuotes = $data->GlobalQuotes;

//print_r($aGlobalQuotes);

	// Step through the day's data and assign the values we need
	foreach($aGlobalQuotes as $key=>$quote){
		$date	= date("Ymd", strtotime($quote->Date));
		$last	= $quote->Last;
		$open	= $quote->Open;
		$high	= $quote->High;
		$low	= $quote->Low;
		$volume	= $quote->Volume;

//echo $date."\n";
//echo $last."\n";
//echo $open."\n";
//echo $high."\n";
//echo $low."\n";
//echo $volume."\n\n";

		// Insert the day's data
		$query = "
			INSERT INTO stocks_benchmarks_history (
				symbol,
				date,
				unix_date,
				open,
				high,
				low,
				close,
				volume,
				timestamp
			) VALUES (
				:symbol,
				:date,
				:unix_date,
				:open,
				:high,
				:low,
				:close,
				:volume,
				UNIX_TIMESTAMP()
			)
		";
		try {
			$rsInsert = $mLink->prepare($query);
			$aValues = array(
				':symbol'	=> $symbol,
				':date'		=> $date,
				':unix_date'=> mktime(5,0,0,substr($date,4,2),substr($date,6,2),substr($date,0,4)),
				':open'		=> $open,
				':high'		=> $high,
				':low'		=> $low,
				':close'	=> $last,
				':volume'	=> $volume
			);
			//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//die($preparedQuery);
			$rsInsert->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

	}

}

echo "Done!\n";

?>