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

// Make a copy of the members_fund_pricing table
$table = $fund_pricing_table."_".rand(0, 65535);
$query = "
	CREATE TABLE ".$table."
	LIKE ".$fund_pricing_table."
";
//die($query);
try{
	$rsCreate = $mLink->prepare($query);
	$rsCreate->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Delete the secondary (oldest) backup copy of the members_fund_pricing table
$query = "
	DROP TABLE ".$fund_pricing_table."_backup2
";
//die($query);
try{
	$rsDrop = $mLink->prepare($query);
	$rsDrop->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Rename the backup as backup2
$query = "
	RENAME TABLE ".$fund_pricing_table."_backup
	TO ".$fund_pricing_table."_backup2
";
//die($query);
try{
	$rsRename = $mLink->prepare($query);
	$rsRename->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}


// RENAME LIVE TABLE AS BACKUP AND RENAME THE TEMP TABLE AS THE LIVE TABLE AT THE END


// Get a list of all the fund ids
$query = "
	SELECT distinct fund_id
	FROM ".$fund_pricing_table."
	WHERE fund_id <> ''
	ORDER BY CAST(fund_id as SIGNED INTEGER) ASC
";
//die($query);
try{
	$rsFundIDs = $mLink->prepare($query);
	$rsFundIDs->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Step through them
while ($fundID = $rsFundIDs->fetch(PDO::FETCH_ASSOC)){

//if ($fundID['fund_id'] != "9-1"){
//	continue;
//}
	// Get all the non-duplicate rows for the fund
	$query = "
		SELECT * FROM ".$fund_pricing_table." fpt
			INNER JOIN (SELECT MAX(uid) AS xid
						FROM ".$fund_pricing_table."
						WHERE fund_id = '".$fundID['fund_id']."'
						GROUP BY fund_id, date) j
			ON fpt.uid = j.xid
		ORDER BY fund_id ASC, date ASC
	";

	//die($query);
	try{
		$rsFundHistory = $mLink->prepare($query);
		$rsFundHistory->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

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


}

// RENAME LIVE TABLE AS BACKUP AND RENAME THE TEMP TABLE AS THE LIVE TABLE...


?>