<?php
// This commandline batch script grabs all the data from Ken's original CRM system "manager" table and populates the new system's tables with it.
// *WARNING - It is destructive in that running it WILL insert rows of data blindly.  Running it multiple times will result in multiple rows for each member...it's supposed to be a "one-off" utility.
//
// Example:
//	/usr/bin/php /var/www/html/batch/getManagersForMCRM.php
// *Note - this will not run within a web browser.

// Debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Global system functions
require("/var/www/html/includes/systemFunctionsPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");


// Get the last closing price
$query = "
	SELECT *
	FROM manager
";
//where Login = 'jeffsaunders'
try{
	$rsManager = $cLink->prepare($query);
	$rsManager->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

while($manager = $rsManager->fetch(PDO::FETCH_ASSOC)){

//echo $manager["Login"]."\n";

	// Create manager record
	$query = "
		INSERT INTO mcrm_user_jeff (
			name_first,
			name_last,
			member_managerkey,
			member_username,
			member_pw,
			last_login,
			active,
			timestamp
		)VALUES(
			:name_first,
			:name_last,
			:managerkey,
			:username,
			:pw,
			:last_login,
			1,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $cLink->prepare($query);
		$aValues = array(
			':name_first'	=> addslashes($manager["FirstName"]),
			':name_last'	=> addslashes($manager["LastName"]),
			':managerkey'	=> $manager["ManagerKey"],
			':username'		=> addslashes($manager["Login"]),
			':pw'			=> addslashes($manager["pw"]),
			':last_login'	=> $manager["LastLogin"]
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Get the user_id just assigned
	$uid = $cLink->lastInsertId();

//echo $uid;

	// Create flags record
	$query = "
		INSERT INTO mcrm_user_flag_jeff (
			user_id,
			flag_member,
			timestamp
		)VALUES(
			:user_id,
			1,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $cLink->prepare($query);
		$aValues = array(
			':user_id'	=> $uid
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Create email record
	$query = "
		INSERT INTO mcrm_user_email_jeff (
			user_id,
			email_address,
			email_default,
			timestamp
		)VALUES(
			:user_id,
			:email,
			1,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $cLink->prepare($query);
		$aValues = array(
			':user_id'	=> $uid,
			':email'	=> addslashes($manager["Email"])
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	if (!is_null($manager["Email2"]) && $manager["Email2"] != ""){

		// Create secondary email record
		$query = "
			INSERT INTO mcrm_user_email_jeff (
				user_id,
				email_address,
				email_default,
				timestamp
			)VALUES(
				:user_id,
				:email,
				0,
				UNIX_TIMESTAMP()
			)
		";
		try{
			$rsInsert = $cLink->prepare($query);
			$aValues = array(
				':user_id'	=> $uid,
				':email'	=> addslashes($manager["Email2"])
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery."\n";
			$rsInsert->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

	}

	// Create address record
	$query = "
		INSERT INTO mcrm_user_address_jeff (
			user_id,
			city,
			state,
			country,
			zip,
			address_1,
			address_2,
			`default`,
			timestamp
		)VALUES(
			:user_id,
			:city,
			:state,
			:country,
			:zip,
			:address_1,
			:address_2,
			1,
			UNIX_TIMESTAMP()
		)
	";
	try{
		$rsInsert = $cLink->prepare($query);
		$aValues = array(
			':user_id'	=> $uid,
			':city'		=> addslashes($manager["City"]),
			':state'	=> addslashes($manager["State"]),
			':country'	=> addslashes($manager["Country"]),
			':zip'		=> addslashes($manager["Zip"]),
			':address_1'=> addslashes($manager["Street1"]),
			':address_2'=> addslashes($manager["Street2"])
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery."\n";
		$rsInsert->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Get their new portfolio member_id, if they have one
	$query = "
		SELECT member_id
		FROM members
		WHERE fb_primarykey  = 'X\'".$manager["ManagerKey"]."\''
	";
	try{
		$rsMemberID = $mLink->prepare($query);
		$rsMemberID->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	if ($rsMemberID->rowCount() > 0){

		$ID = $rsMemberID->fetch(PDO::FETCH_ASSOC);

		$query = "
			UPDATE mcrm_user_jeff
			SET	member_platform_id = :member_id
			WHERE member_managerkey = :manager_key
		";
		try {
			$rsUpdate = $cLink->prepare($query);
			$aValues = array(
				':member_id'	=> $ID["member_id"],
				':manager_key'	=> $manager["ManagerKey"]

			);
			// Prepared query - for error logging and debugging
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;
			$rsUpdate->execute($aValues);
		}
		catch(PDOException $error){
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}


	}

//die();

}

?>