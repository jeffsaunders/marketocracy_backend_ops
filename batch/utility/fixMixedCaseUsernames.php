<?php
/*
The purpose of this script is to find mixed case usernames and email addresses in the AUTH records and re-encrypt them in all lower case
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


// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);
$task = $_REQUEST['task'];

//echo $task;die();
switch($task) {

	case "authentication":
	// Convert any mixed case (or all upper case) usernames to all lower case in the system_authentication table

		$query =
			"SELECT *
			 FROM system_authentication
			 WHERE 1
		";
		try{
			$rsAuth = $mLink->prepare($query);
			$rsAuth->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		while ($auth = $rsAuth->fetch(PDO::FETCH_ASSOC)){

			$member_id = $auth['member_id'];
			$clear_username = decrypt($auth['username']);
			$clear_email = decrypt($auth['email']);
			$encrypted_username = encrypt(strtolower($clear_username));
			$encrypted_email = encrypt(strtolower($clear_email));

			$query = "
				UPDATE system_authentication
				SET username = '".$encrypted_username."',
					email = '".$encrypted_email."'
				WHERE member_id = :member_id
			";
			try {
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
					':member_id' => $member_id
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//die($preparedQuery);
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

		}


	case "members":
	// Convert any mixed case (or all upper case) usernames to all lower case in the members table

		$query =
			"SELECT member_id, username, email
			 FROM members
			 WHERE 1
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

			$member_id = $member['member_id'];
			$username = strtolower($member['username']);
			$email = strtolower($member['email']);

			$query = "
				UPDATE members
				SET username = '".$username."',
					email = '".$email."'
				WHERE member_id = :member_id
			";
			try {
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
					':member_id' => $member_id
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//die($preparedQuery);
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

		}


	case "clear_passwords":
	// Convert any mixed case (or all upper case) usernames to all lower case in the clear_passwords table

		$query =
			"SELECT username, uid
			 FROM clear_passwords
			 WHERE 1
		";
		try{
			$rsUnames = $mLink->prepare($query);
			$rsUnames->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		while ($uname = $rsUnames->fetch(PDO::FETCH_ASSOC)){

			$uid = $uname['uid'];
			$username = strtolower($uname['username']);

			$query = "
				UPDATE clear_passwords
				SET username = '".$username."'
				WHERE uid = :uid
			";
			try {
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
					':uid' => $uid
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//die($preparedQuery);
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

		}


	case "repair_auth":
	// Fix a screw up caused by running a routine to create missing auth records (fixMissingAuth.php) BEFORE running the above fix to convert them all to lower case.
	// Basically, that process looked for each encrypted username converted to all lower case and any that were mixed case were not a match, thus "missing", so a new auth record was created using the default password of "newapplicationlogin" ("NQaEliKx4h25b6H6rRc/xYf0GfURrO0hWhhbqgG9Bxg=" encrypted), effectively changing their password.
	// This process finds those (where there actually was a previous record) and deletes them, rolling them back to their previous passwords.

		$query =
			"SELECT *
			 FROM system_authentication
			 WHERE password = 'NQaEliKx4h25b6H6rRc/xYf0GfURrO0hWhhbqgG9Bxg='
		";
		try{
			$rsAuth = $mLink->prepare($query);
			$rsAuth->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		while ($auth = $rsAuth->fetch(PDO::FETCH_ASSOC)){

			$uid = $auth['uid'];
			$encrypted_username = $auth['username'];

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

			if ($rsExists->rowCount() > 1){ // It existed before!

				$query =
					"DELETE
					 FROM system_authentication
					 WHERE uid = '".$uid."'
				";
				try{
					$rsDelete = $mLink->prepare($query);
					$rsDelete->execute();
				}
				catch(PDOException $error){
					// Log any error
					file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
				}
//echo decrypt($encrypted_username)."\n";
//die();
			}

		}


} //End switch

?>