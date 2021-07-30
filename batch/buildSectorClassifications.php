<?php
/*
The purpose of this script is to populate the stocks_sub_sectors table with the FactSet based values gleaned from the Xignite feed and assign them a sector value (stocks_sectors) gleaned from GICS.
*Note - this will not run within a web browser.
*/

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

//Open the source spreadsheet assigning the sectors to each subsector
$aSectors = array_map('str_getcsv', file('/root/Sector Classifications.csv'));

//print_r($aSectors);

// Starting subsector ID
$subSectorID = 1;

foreach($aSectors as $key=>$aSector){

	// Get the assigned sector_id
	$query = "
		SELECT sector_id
		FROM stocks_sectors
		WHERE sector_name = '".$aSector[0]."'
	";
	try{
		$rsSectorIDs = $mLink->prepare($query);
		$rsSectorIDs->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$sectorIDs = $rsSectorIDs->fetch(PDO::FETCH_ASSOC);

//echo $aSector[1]." -> ".$aSector[0]." (".$sectorIDs['sector_id'].")\n";

	// Turn the subsector ID into a 3 character zero padded string
	$subSectorString = sprintf("%'.03d", $subSectorID++);
//echo $subSectorString."\n";

	// Insert the subsector record
	$query = "
		INSERT INTO stocks_sub_sectors (
			sub_sector_id,
			sub_sector_name,
			sector_id,
			active,
			effective_timestamp
		)VALUES(
			:sub_sector_id,
			:sub_sector_name,
			:sector_id,
			1,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $mLink->prepare($query);
		$aValues = array(
			':sub_sector_id'		=> $subSectorString,
			':sub_sector_name'		=> $aSector[1],
			':sector_id'			=> $sectorIDs['sector_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";die();
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

}

?>