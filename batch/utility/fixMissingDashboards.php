<?php
// This commandline batch script builds a fresh members_fund_pricing table containing only unique rows - effectively removing duplicates
// *Note - this will not run within a web browser.
die();  // Stop accidental execution.
// Load any global functions
require("/var/www/html/includes/systemDebugFunctions.php");
require("/var/www/html/includes/systemFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Get all the validated auth records
$query = "
	SELECT distinct member_id
	FROM ".$auth_table."
	WHERE email_validated_timestamp <> 0
";
//die($query);
try{
	$rsMemberIDs = $mLink->prepare($query);
	$rsMemberIDs->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($memberID = $rsMemberIDs->fetch(PDO::FETCH_ASSOC)){

	// See if they have a settings record
	$query = "
		SELECT * FROM ".$members_settings_table."
		WHERE member_id = '".$memberID['member_id']."'
	";

	//die($query);
	try{
		$rsSettings = $mLink->prepare($query);
		$rsSettings->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// If they don't, write one
	if ($rsSettings->rowCount() == 0){
echo $memberID['member_id']."\n";

		// Write their default dashboard settings
		$query = "
			INSERT INTO ".$members_settings_table." (
				member_id,
				dash_col1,
				dash_col2,
				dash_4col1,
				dash_4col2,
				timestamp
			) VALUES (
				:member_id,
				:dash_col1,
				:dash_col2,
				:dash_4col1,
				:dash_4col2,
				UNIX_TIMESTAMP()
			)
		";
		try {
			$rsInsert = $mLink->prepare($query);
			$aValues = array(
				':member_id'	=> $memberID['member_id'],
				':dash_col1'	=> "notifications~0~0~0",
				':dash_col2'    => "tickers~0~0~0",
				':dash_4col1'	=> "notifications~0~0~0",
				':dash_4col2'   => "tickers~0~0~0"
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//die($preparedQuery);
			$rsInsert->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

//die();
	}

/*


	// Write the deduped rows to the new table
	$query =
		"INSERT INTO ".$table." (
			fund_id,
			timestamp,
			date,
			unix_date,
			startCash,
			positionsValue,
			cashValue,
			totalValue,
			price,
			shares,
			inFlow,
			outFlow,
			netFlow,
			tradeBuys,
			tradeSells,
			tradeValue,
			tradeRatio,
			interest,
			fees,
			dividends,
			compliance,
			secCompliance,
			welterCompliance,
			featherCompliance,
			missingPriceCount,
			violatesCash35,
			violatesDiversification25,
			violatesDiversification10,
			isInMargin,
			tradedAbove25
		) VALUES ";

	// Step through the rows and build the rest of the insert
	while ($history = $rsFundHistory->fetch(PDO::FETCH_ASSOC)){

		$query .= "(
					'".$fundID['fund_id']."',
					".$history['timestamp'].",
					'".$history['date']."',
					".$history['unix_date'].",
					".$history['startCash'].",
					".$history['positionsValue'].",
					".$history['cashValue'].",
					".$history['totalValue'].",
					".$history['price'].",
					".$history['shares'].",
					".$history['inFlow'].",
					".$history['outFlow'].",
					".$history['netFlow'].",
					".$history['tradeBuys'].",
					".$history['tradeSells'].",
					".$history['tradeValue'].",
					".$history['tradeRatio'].",
					".$history['interest'].",
					".$history['fees'].",
					".$history['dividends'].",
					".$history['compliance'].",
					".$history['secCompliance'].",
					".$history['welterCompliance'].",
					".$history['featherCompliance'].",
					".$history['missingPriceCount'].",
					".$history['violatesCash35'].",
					".$history['violatesDiversification25'].",
					".$history['violatesDiversification10'].",
					".$history['isInMargin'].",
					".$history['tradedAbove25']."
				),";
	}

	// Pop the trailing "," off
	$query = substr($query, 0, -1);

	//echo $query;
	try{
		$rsInsert = $mLink->prepare($query);
		$rsInsert->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}


//if ($fundID['fund_id'] == "9-2"){
//	die();
//}

*/
}

// RENAME LIVE TABLE AS BACKUP AND RENAME THE TEMP TABLE AS THE LIVE TABLE...


?>