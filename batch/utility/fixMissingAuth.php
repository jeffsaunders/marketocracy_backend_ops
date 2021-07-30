<?php
/*
The purpose of this script is to find missing AUTH records for existing members and add them so they can log in
This must be run on one of the new servers as the version of the MCRYPT library on the Fetch server is too old to support our encryption settings.
*/
die();  // Stop accidental execution.
// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

$start_port = 52100;
$stop_port = 52499;
$port = rand($start_port, $stop_port);
$counter = 0;

$query =
	"SELECT username, email, member_id
	 FROM members
	 WHERE active = 1
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while ($member = $rsMembers->fetch(PDO::FETCH_ASSOC)){

	$username = $member['username'];
	$encrypted_username = encrypt($username);
	$email = $member['email'];
	$member_id = $member['member_id'];
echo $member_id." - ".$username."\n";
	$query =
		"SELECT *
		 FROM system_authentication
		 WHERE username = '".$encrypted_username."'
	";
	try{
		$rsExists = $mLink->prepare($query);
		$rsExists->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	if ($rsExists->rowCount() < 1){

		$query =
			"SELECT password
			 FROM clear_passwords
			 WHERE LOWER(username) = '".$username."'
		";
		try{
			$rsPassword = $mLink->prepare($query);
			$rsPassword->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$pass = 0;

		while ($rsPassword->rowCount() < 1){

			// Go get their password
			$query = "managerPassword|0|".$username;

			// Set the port number for the API call
			if ($port == $stop_port){
				$port = $start_port;
			}else{
				$port++;
			}

			// Call on the API Daemon via an EXPECT script
			$cmd = '/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
			exec($cmd);

			// Now look for it every 5 seconds 12 times (1 minute)
			for ($cnt = 0; $cnt < 11; $cnt++){

				// Wait 5 ticks
				sleep(5);

				$query =
					"SELECT password
					 FROM clear_passwords
					 WHERE LOWER(username) = '".$member['username']."'
				";
				try{
					$rsPassword = $mLink->prepare($query);
					$rsPassword->execute();
				}
				catch(PDOException $error){
					// Log any error
					file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				}

				if ($rsPassword->rowCount() > 0){ // Got it!
					break 2;
				}

//				if ($cnt > 11){ // It never came...
	//				$message .= "Password for <strong><em>\"".$username."\"</em></strong> is missing - please create his authentication record manually.<br>";
//					break;
//				}
			}
			$pass++;
//			if ($pass > 3){
			if ($pass > 0){
				break;
			}
		}

		$pass = $rsPassword->fetch(PDO::FETCH_ASSOC);
		$password = $pass['password'];

		// Use the legacyDataDaemon to signal the process server to create and populate the member's authentication record
		$query = "importPassword|".$username."|".$password."|".$email."|".$member_id;

		// Set the port number for the API call
		if ($port == $stop_port){
			$port = $start_port;
		}else{
			$port++;
		}

		// Call on the API Daemon via an EXPECT script
		$cmd = '/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
		exec($cmd);

		$counter++;
	}
}

echo $counter." Authentication Records Created.";

?>