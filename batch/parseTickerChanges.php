<?php
/*
Open text file
read each line
look for "TIMESTAMP '" and grab the next 10 characters -> date
look for "BB_ACTIONDICTIONARY" - if found
	read in entire line
	explode on "|"
	grab array(1) -> old ticker
	grab array(3) -> new ticker
	explode each on " " and grab array(0) as actual ticker

*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Functions
function get_funds($mLink, $fundID){

	$query = "
		SELECT *
		FROM members_fund
		WHERE fund_id = :fund_id
		AND active = 1
	";

	try{
		$rsGetFund = $mLink->prepare($query);
		$aValues = array(
			//':member_id' 	=> $_SESSION['member_id'],
			':fund_id'		=> $fundID

		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."<br>";
		$rsGetFund->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$fundInfo = $rsGetFund->fetch(PDO::FETCH_ASSOC);
	return $fundInfo['fund_symbol'];
}

function get_member($mLink, $memberID){

//	$query = "
//		SELECT *
//		FROM ".$members_table." as m
//		INNER JOIN ".$members_profile_table." as mp ON m.member_id = mp.member_id
//		WHERE m.member_id = :member_id
//		ORDER BY version DESC LIMIT 1
//	";
	$query = "
		SELECT username
		FROM members
		WHERE member_id = :member_id
	";
	try{
		$rsUser = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $memberID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."<br>";
		$rsUser->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$user = $rsUser->fetch(PDO::FETCH_ASSOC);

	return $user['username'];
}

// Ok, parse text file from Marty
$input = @fopen("../tmp/CHG_TKR since 2015-08-01.txt", "r");
//$output = fopen("/var/www/html/tmp/CHG_TKR_since_2015-08-01.csv", "w");
$aRows = array();
$rowNum = 0;
if ($input) {
	while (($buffer = fgets($input, 4096)) !== false) {
		if (strpos($buffer, "> Row") > 0){
			continue;
		}
		if (stripos($buffer, "TIMESTAMP '") > 0){
			$date = substr($buffer, stripos($buffer, "TIMESTAMP '") + 11, 10);
//echo $date."&nbsp;&nbsp;";
			continue;
		}
		if (stristr($buffer, "BB_ACTIONDICTIONARY")){
			$aTickers = explode("|", $buffer);
//echo $aTickers[1]."&nbsp;&nbsp;".$aTickers[3]."<br>";
			$oldTicker = substr($aTickers[1], 0, -3);
			$newTicker = substr($aTickers[3], 0, -3);
//echo $oldTicker."&nbsp;&nbsp;".$newTicker."<br>";

			$aRows[$rowNum][0] = $date;
			$aRows[$rowNum][1] = $oldTicker;
			$aRows[$rowNum][2] = $newTicker;

			$rowNum++;
//			$row = '"'.$date.'","'.$oldTicker.'","'.$newTicker."\r";
//			fwrite($output, $row, strlen($row)+1);
		}
	}
}


//print_r(array_reverse($aRows));
$aRows = array_reverse($aRows);

// Set up port range
$start_port = rand(52100, 52499); // API2
$stop_port = 52499;

// Initialize current port
$port = $start_port;

for ($cnt = 0; $cnt < $rowNum; $cnt++){
//	$row = '"'.$aRows[$cnt][0].'","'.$aRows[$cnt][1].'","'.$aRows[$cnt][2].'"';
//	$row .= "\r\n";
//	fwrite($output, $row, strlen($row)+1);

	$oldTicker = $aRows[$cnt][1];
	$newTicker = $aRows[$cnt][2];
//$oldTicker = "LUV";
	// Get all funds holding the old symbol
	$query = "
		SELECT fund_id
		FROM ".$fund_stratification_basic_table."
		WHERE stockSymbol = :stockSymbol
	";
	try{
		$rsFunds = $mLink->prepare($query);
		$aValues = array(
			':stockSymbol' 	=> $oldTicker
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		echo $preparedQuery."<br>";
		$rsFunds->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$aFundIds = array();
	while($funds = $rsFunds->fetch(PDO::FETCH_ASSOC)){
//echo $funds['fund_id']."<br>";
		// Store all IDs into an array for logging at the end
		$aFundIds[] = $funds['fund_id'];

		// Define some vars
		$fundID		= $funds['fund_id'];
		$fundSymbol	= get_funds($mLink, $fundID);
		$aFundID 	= explode('-',$fundID);
		$memberID	= $aFundID[0];
echo $memberID."<br>";
		$username	= get_member($mLink, $memberID);
echo $username."<br>";
		// Dump the old trades
		$query = "
			DELETE FROM ".$fund_trades_table."
			WHERE fund_id = :fund_id
			AND stockSymbol = :stockSymbol
		";
		try{
			$rsDelete = $mLink->prepare($query);
			$aValues = array(
				':stockSymbol' 	=> $oldTicker,
				':fund_id'		=> $fundID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			echo $preparedQuery."<br>";
			$rsDelete->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Dump the old basic strat
		$query = "
			DELETE FROM ".$fund_stratification_basic_table."
			WHERE fund_id = :fund_id
			AND stockSymbol = :stockSymbol
		";
		try{
			$rsDelete = $mLink->prepare($query);
			$aValues = array(
				':stockSymbol' 	=> $oldTicker,
				':fund_id'		=> $fundID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			echo $preparedQuery."<br>";
			$rsDelete->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Dump the old positions details
		$query = "
			DELETE FROM ".$fund_positions_details_table."
			WHERE fund_id = :fund_id
			AND stockSymbol = :stockSymbol
		";
		try{
			$rsDelete = $mLink->prepare($query);
			$aValues = array(
				':stockSymbol' 	=> $oldTicker,
				':fund_id'		=> $fundID
				);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			echo $preparedQuery."<br>";
			$rsDelete->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Run a new positionInfo for this fund
		$query = 'positionInfo|0|'.$username.'|'.$fundID.'|'.$fundSymbol.'|'.$newTicker;
echo $query."<br>";

		// Set the port number for the API call
		if ($port >= $stop_port){
			$port = $start_port;
			sleep(1);
		}else{
			$port++;
		}

		// Include the API call
		include("/var/www/html/".$_SESSION['base_url']."web/includes/data-query-legacy.php");

		// Run a new tradesForPosition for this fund
		$query = 'tradesForPosition|0|'.$username.'|'.$fundID.'|'.$fundSymbol.'|'.$newTicker;
//$query = 'tradesForFund|0|'.$username.'|'.$fundID.'|'.$fundSymbol;
echo $query."<br>";

		// Set the port number for the API call
		if ($port >= $stop_port){
			$port = $start_port;
			sleep(1);
		}else{
			$port++;
		}

		// Include the API call
		include("/var/www/html/".$_SESSION['base_url']."web/includes/data-query-legacy.php");

//break;

	}

	// Log the CA application
	$query = "
		INSERT INTO ".$ca_affected_funds_table." (
			ca_type,
			fund_ids,
			old_stock_symbol,
			stock_symbol,
			timestamp
		)VALUES(
			'ticker_change',
			:fund_ids,
			:old_stock_symbol,
			:stock_symbol,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':old_stock_symbol' 	=> $oldTicker,
			':stock_symbol'			=> $newTicker,
			':fund_ids'				=> implode('|',$aFundIds)
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		echo $preparedQuery."<br>";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}




//break;

}

fclose($input);
//fclose($output);

echo "Done.";

?>