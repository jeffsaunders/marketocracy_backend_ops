<?php
// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
require("/var/www/html/includes/systemFunctions.php");

// Name the output files
$fName = "/var/www/html/tmp/Imported-Members-Email-List.csv";

// Create the outputs files
$fOut = fopen($fName, "w");

// Write header rows
$headers = "ManagerKey,Email\r\n";
fwrite($fOut, $headers);

$query = "
    SELECT fb_primarykey as managerkey, email
	FROM members
	WHERE active = 1
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($member = $rsMembers->fetch(PDO::FETCH_ASSOC)){

//print_r($member);die();
	$managerKey = strtoupper(substr($member['managerkey'], 2, 24));
//	$managerKey = $member['managerkey'];

	// Start building the row
	$row = '"'.$managerKey.'","'.$member['email'].'"';

	$row .= "\r\n";

	fwrite($fOut, $row);

}

// Close 'er up
fclose($fOut);

?>
