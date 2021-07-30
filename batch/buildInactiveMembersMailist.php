<?php
/*
The purpose of this script is to populate a CSV wiht the data needed to do a mass mailing to all inactive members (based on a list of email addresses as input).
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
$aEmails = array_map('str_getcsv', file('/root/Inactive-marketocracy-membership.csv'));

//print_r($aEmails);

// Name the output files
$fName = "/var/www/html/tmp/Inactive-marketocracy-membership-2.csv";

// Create the output file
$fOut = fopen($fName, "w");

// Write header rows
$headers = "Email,FirstName,UserName\r\n";
fwrite($fOut, $headers);

foreach($aEmails as $key=>$aEmail){

	// Get the
	$query = "
		SELECT first_name, username
		FROM clear_passwords
		WHERE email = '".$aEmail[0]."'
	";
	try{
		$rsNames = $mLink->prepare($query);
		$rsNames->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	$names = $rsNames->fetch(PDO::FETCH_ASSOC);

	// Build the row
	$row = '"'.$aEmail[0].'","'.$names['first_name'].'","'.$names['username'].'"';
	$row .= "\r\n";

	// Write it
	fwrite($fOut, $row);

}

// Close 'er up
fclose($fOut);

// c'est fini
echo "Done!\n"

?>