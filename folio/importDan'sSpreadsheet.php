<?php
// This commandline batch script reads a CSV extract of Dan's Composite Disclosure Construction spreadsheet and parses the data for insertion into the members_fund_composite table.
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
//require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
//require("/var/www/html/includes/systemFunctions.php");

// Handy function for converting Excel timestamps (~unixtime) to actual dates
function ExcelToPHPObject($excelDateTime = 0) {
	$calendarBaseLine = '1899-12-30';
	if ($excelDateTime < 60) {
		// 29th February will be treated as 28th February
		++$excelDateTime;
	}

	return (new \DateTime($calendarBaseLine, new \DateTimeZone('UTC')))
		->modify('+' . floor($excelDateTime) . ' days')
		->modify('+' . floor(fmod($excelDateTime, 1) * 86400) . ' seconds');
}

// Initialize the file object for processing
$file = new SplFileObject('Composite Disclosure Construction - Main2018 - Summary.csv');

$rowCnt = 1;
//$yearCnt = 0;
while (!$file->eof()){

	// Read the file, line by line, turning each line into an array
	$row = $file->fgetcsv(",");

	// If it's the header row, grab the manager's usernames
	if ($rowCnt == 1){

		$aUsernames = $row;
//		print_r($aUsernames);
		$rowCnt++;
		continue;

	}

	// If it's the second row, grab the fund symbols
	if ($rowCnt == 2){

		$aFundSymbols = $row;
//		print_r($aFundSymbols);
		$rowCnt++;
		continue;

	}

	// If the first cell in the row is not a number, skip it
	//if (!is_numeric(substr($row[0], 0, -1) + 0)){
	// Not working as it should so just look for the first five digits to be 42035 (1/31/15) or greater  - good enough
	if (substr($row[0], 0, 5) + 0 < 42035){

		$rowCnt++;
		continue;

	}else{

		//Convert the Excel date value to a standard datetime (sans time) using the custom function defined above
		$datetime = ExcelToPHPObject($row[0]/100)->format('Y-m-d');

		// Turn the datetime into a timestamp
		list($year, $month, $day) = explode('-', $datetime);  // Extract the date elements
		$unix_date = mktime(0, 0, 0, $month, $day, $year);

		// Replace the value in cell 0 with the timestamp for easier processing below
		$row[0] = $unix_date;

		// Push the row onto the array
		$aYears[] = $row;

		$rowCnt++;

	}

}

//print_r($aYears);

// Get the fund IDs

for ($i = 0; $i < count($aUsernames); $i++) {

	// query database
	$query = "
		SELECT fund_id
		FROM composite_cassatt_list
		WHERE username = :username
		AND fund_symbol = :symbol
	";
	// ... or the following, whichever ends up being more reliable
//	$query = "
//		SELECT f.fund_id
//		FROM members_fund f, members m
//		WHERE f.member_id = m.member_id
//		AND m.username = :username
//		AND f.fund_symbol = :symbol
//	";
	try{
		$rsFundID = $mLink->prepare($query);
		$aValues = array(
			':username'	=> $aUsernames[$i],
			':symbol'	=> $aFundSymbols[$i]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo "[$i] - \n".$preparedQuery;//die();
		$rsFundID->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$fund_id = $rsFundID->fetch(PDO::FETCH_ASSOC);

	// If the fund_id is found add it to the array at the $i position
  	$aFundIDs[] = $fund_id['fund_id'];

}

//print_r($aFundIDs);

// Step through $aFundIDs and update the database with the $aYears values if we have a fund_id, skip those without one.
// Fgure out the composite_calc value!  comp/100?
//print_r($aYears[0]);


foreach($aYears as $key=>$aComps){

//	echo "[$key] -> ".$aComps[0]."\n";

	for ($i = 0; $i < count($aUsernames); $i++) {

		if ($aFundIDs[$i] == ""){
			continue;
		}
//echo $aFundIDs[$i].", ".$aUsernames[$i].", ".$aFundSymbols[$i].", ".$aComps[$i]."\n";

		$composite = (substr($aComps[$i], 0, -1) * 1);

		if ($composite == 0){
			continue;
		}

		$compCalc = $composite / 100;

//echo $aFundIDs[$i].", ".$aUsernames[$i].", ".$aFundSymbols[$i].", ".$composite.", ".$compCalc."\n";

		$query = "
			UPDATE members_fund_composite_jeff
			SET composite = :composite,
				composite_calc = :compCalc
			WHERE fund_id = :fund_id
			AND unix_date = :unix_date
		";
		try{
			$rsUpdate = $mLink->prepare($query);
			$aValues = array(
				':composite'	=> $composite,
				':compCalc'	=> $compCalc,
				':fund_id'	=> $aFundIDs[$i],
				':unix_date'	=> $aComps[0]
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo "[$i] - \n".$preparedQuery;//die();
			$rsUpdate->execute($aValues);
		}


/*
		$query = "
			UPDATE members_fund_composite_jeff
			SET composite = ".$composite.",
				composite_calc = ".$compCalc."
			WHERE fund_id = '".$aFundIDs[$i]."'
			AND unix_date = ".$aComps[0]."
		";
		try{
			$rsUpdate = $tLink->prepare($query);
//			$aValues = array(
//				':composite'	=> $composite,
//				':compCalc'	=> $compCalc,
//				':fund_id'	=> $aFundIDs[$i],
//				':unix_date'	=> $aComps[0]
//			);
//			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo "[$i] - \n".$preparedQuery;//die();
			$rsUpdate->execute();
		}
*/
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

//		sleep(1);


	}



//	if ($key > 0){
//		break;
//	}

}

/*
for ($i = 0; $i < count($aUsernames); $i++) {

	if ($aFundIDs[$i] == ""){
		continue;
	}

//	echo $aFundIDs[$i].", ".





}

*/

//}


/*

$excelDateTime = 4203500;
//echo ExcelToPHPObject($excelDateTime)->format('Y-m-d H:i:s');
$datetime = ExcelToPHPObject($excelDateTime/100)->format('Y-m-d H:i:s');
$date = ExcelToPHPObject($excelDateTime/100)->format('Ymd');
//$unixdate = ExcelToPHPObject($excelDateTime)->format('U');
// That doesn't work for some reason so here's the long way
list($year, $month, $day) = explode('-', $datetime);  // Extract the date elements
$unix_date = mktime(0, 0, 0, $month, $day, $year);



//echo $date.",".$unix_date.",".$datetime."\n";

*/
?>