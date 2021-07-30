<?php
/*
The purpose of this script is to extract account information from Frontbase and insert into MySQL, effectively creating accounts
It must be run through Apache, thus through a browser in order for the FrontBase library to be accessible, unfortunately
*/

// OK, let's get going...

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run forever
set_time_limit(30);

// Load encryption functions
//require("../includes/crypto.php");

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www.html/includes/dbConnect.php");

// Connect to FrontBase
$dbHost = "192.168.111.141"; // Live (db1)
$dbUser = "EOUSER";
$dbPass = ""; // No password
$dbName = "MARKETOCRACY";
$dbMarketocracy = fbsql_connect($dbHost, $dbUser, $dbPass) or die("Could not connect to FrontBase");
fbsql_select_db($dbName, $dbMarketocracy) or die("Could not select ".$dbName." DB in FrontBase");

// CHANGE $member_count TO THE STARTING NUMBER OF THE NEXT BLOCK YOU WANT TO IMPORT!
// CHANGE THE VALUE AFTER THE < TO THE QUANTITY
// Imported 0-199 9/29/14
// Imported 200-399 10/7/14
//for ($member_count = 0; $member_count < 200; $member_count += 25){
for ($member_count = 300; $member_count < 400; $member_count += 25){
	// Go get everyone who has logged in this year
	//$query = "
	//	SELECT top 10 *
	//	FROM EOUSER.MMANAGER M
	//		INNER JOIN EOUSER.MADDRESS A
	//		ON M.HOMEADDRESSKEY = A.PRIMARYKEY
	//	WHERE CAST(LASTLOGIN as char(25)) > '2013-12-31 23:59:59';
	//";  NO JOINS!!!!!!!!!
	$query = "
		SELECT top(".$member_count.", 25) *
		FROM EOUSER.MMANAGER M
		WHERE CAST(LASTLOGIN as char(25)) > '2013-12-31 23:59:59';
	";

	//die($query);
	//echo $query."<br><br>";
	$rs_rows = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");

	//$x = 0;
	// Step through them all
	while ($row = fbsql_fetch_array($rs_rows)){
	//	print_r($row);
	//	echo "<br><br>";
	//echo ++$x."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

		// Get their address
		$query = "
			SELECT *
			FROM EOUSER.MADDRESS
			WHERE PRIMARYKEY = ".trim($row['HOMEADDRESSKEY']).";
		";
		//die($query);
		$rs_address = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
		$aAddress = fbsql_fetch_array($rs_address);

		// Release the result set memory (FrontBase PHP library bug)
		unset($rs_address);

		// Get the phone number, if they have one
		$phone_day = "";
		$query = "
			SELECT NUMBER
			FROM EOUSER.MPHONE
			WHERE ADDRESSKEY = ".trim($row['HOMEADDRESSKEY']).";
		";
		//die($query);
		$rs_phone = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
		if (fbsql_num_rows($rs_phone) > 0){
			$phone			= fbsql_fetch_assoc($rs_phone);
			$phone_day		= trim($phone['NUMBER']);
		}
		// Release the result set memory (FrontBase PHP library bug)
		unset($rs_phone);

		// Set their country and country code, or get their country code if they are outside the US
		if ($aAddress['COUNTRY'] == "USA"){
			$country		= "United States";
			$country_code	= "US";
		}else{
			$country		= trim($aAddress['COUNTRY']);
			$query = "
				SELECT country_code_isa
				FROM system_countries
				WHERE country_name = '".trim($aAddress['COUNTRY'])."'
			";
			//die($query);
			$rs_country_code = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
			$code = mysql_fetch_array($rs_country_code);
			$country_code	= trim($code['country_code_isa']);
		}

		// Get their password from the clear_passwords table
	/* Can't do this here, mcrypt is not compiled into PHP...just do this afterward from another server.
		$query = "
			SELECT password
			FROM clear_passwords
			WHERE username = '".trim($row['LOGINNAME'])."'
		";
		//die($query);
		$rs_password = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		$password = mysql_fetch_array($rs_password);
		$encrypted_password	= encrypt(trim($password['password']));
	*/

		// Assign them a member_id
		$query = "
			SELECT MAX(member_id)
			FROM members
		";
		//die($query);
		$rs_member_id = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		$id = mysql_fetch_array($rs_member_id);
		$member_id			= $id[0] + 1;

		// Assign the rest of the values to variables
		$username	  		= trim($row['LOGINNAME']);
	//	$encrypted_username	= encrypt(trim($row['LOGINNAME']));
		$name_first			= trim($row['FIRSTNAME']);
		$name_last			= trim($row['LASTNAME']);
	//	$address			= trim($aAddress['STREETA']).(trim($aAddress['STREETB']) != "" ? ", ".trim($aAddress['STREETB']) : "");
		$address			= trim($aAddress['STREETA']);
		$address2			= trim($aAddress['STREETB']);
		$city				= trim($aAddress['CITY']);
		$state				= strtoupper(trim($aAddress['STATE']));
		$zip_code			= strtoupper(trim($aAddress['ZIPCODE']));
		$email				= trim($row['EMAIL']);
	//	$encrypted_email	= encrypt(trim($row['EMAIL']));
		$joined_timestamp	= mktime(substr($row['JOINEDMARKETOCRACY'], 11, 2), substr($row['JOINEDMARKETOCRACY'], 14, 2), substr($row['JOINEDMARKETOCRACY'], 17, 2), substr($row['JOINEDMARKETOCRACY'], 5, 2), substr($row['JOINEDMARKETOCRACY'], 8, 2), substr($row['JOINEDMARKETOCRACY'], 0, 4));
		$fb_primarykey		= trim($row['PRIMARYKEY']);
		$fb_portfoliokey	= trim($row['PORTFOLIOKEY']);
		$fb_lastlogin		= mktime(substr($row['LASTLOGIN'], 11, 2), substr($row['LASTLOGIN'], 14, 2), substr($row['LASTLOGIN'], 17, 2), substr($row['LASTLOGIN'], 5, 2), substr($row['LASTLOGIN'], 8, 2), substr($row['LASTLOGIN'], 0, 4));

	/*
	echo $username;
	echo "|";
	echo $name_first;
	echo "|";
	echo $name_last;
	echo "|";
	echo $address;
	echo "|";
	echo $address2;
	echo "|";
	echo $city;
	echo "|";
	echo $state;
	echo "|";
	echo $zip_code;
	echo "|";
	echo $email;
	echo "|";
	echo $joined_timestamp;
	echo "|";
	echo $fb_primarykey;
	echo "|";
	echo $fb_portfoliokey;
	echo "|";
	echo $fb_lastlogin;
	echo "|";

	echo $country;
	echo "|";
	echo $country_code;
	echo "|";
	echo $phone_day;
	echo "|";
	echo $member_id;
	echo "<br>";
	*/

		// Check to see if they already have an account set up
		$query = "
			SELECT member_id
			FROM members
			WHERE username = '".$username."'
		";
		//die($query);
		$rs_account = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		if (mysql_num_rows($rs_account) == 0){

			// Create account and write values where they go

			// Create account in members table
			$query =
				"INSERT INTO members (
					member_id,
					active,
					timestamp,
					username,
					name_title,
					name_first,
					name_middle,
					name_last,
					name_suffix,
					address,
					address2,
					city,
					state,
					zip_code,
					country,
					country_code,
					email,
					phone_day,
					phone_evening,
					phone_mobile,
					joined_timestamp,
					fb_primarykey,
					fb_portfoliokey,
					fb_lastlogin,
					last_login
				) VALUES (
					".$member_id.",
					1,
					UNIX_TIMESTAMP(),
					'".addslashes($username)."',
					'',
					'".addslashes($name_first)."',
					'',
					'".addslashes($name_last)."',
					'',
					'".addslashes($address)."',
					'".addslashes($address2)."',
					'".addslashes($city)."',
					'".$state."',
					'".$zip_code."',
					'".$country."',
					'".$country_code."',
					'".addslashes($email)."',
					'".$phone_day."',
					'',
					'',
					".$joined_timestamp.",
					'".addslashes($fb_primarykey)."',
					'".addslashes($fb_portfoliokey)."',
					".$fb_lastlogin.",
					NULL
				)";
			//die($query);
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			// Write their username for later password encryption purposes
			$query =
				"INSERT INTO system_imported_usernames (
					member_id,
					timestamp,
					username
				) VALUES (
					".$member_id.",
					UNIX_TIMESTAMP(),
					'".addslashes($username)."'
				)";
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			// Set the new member's flags
			$query =
				"INSERT INTO members_flags (
					member_id,
					member,
					free,
					beta_tester
				) VALUES (
					".$member_id.",
					1,
					1,
					1
				)";
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			// Create their profile record
			$query =
				"INSERT INTO members_profile (
					member_id,
					version,
					timestamp
				) VALUES (
					".$member_id.",
					1,
					UNIX_TIMESTAMP()
				)";
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			// Now create all their funds

			// Get their funds from FrontBase
			$query = "
				SELECT *
				FROM EOUSER.MFUND
				WHERE PORTFOLIOKEY = ".$fb_portfoliokey.";
			";
			//die($query);
			$rs_funds = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
	//		sleep(1);

			// Build an array of fund colors
			$aColors = array('#39B3D7','#D2322D','#ED9C28','#428BCA','#47A447');
			$color = 0;

			// Step through the funds
			$first_fund = "";
			for ($fund_num = 1; $fund_num < fbsql_num_rows($rs_funds)+1; $fund_num++){
				$fund = fbsql_fetch_assoc($rs_funds);
	//print_r($fund);
				// Assign values to variables
				$fund_color = $aColors[$color];
				$fund_id = $member_id."-".$fund_num;
				$seq_id = $fund_num;
				$inception_date = substr($fund['INCEPTION'], 0, 4).substr($fund['INCEPTION'], 5, 2).substr($fund['INCEPTION'], 8, 2);
				$unix_date	= mktime(substr($fund['INCEPTION'], 11, 2), substr($fund['INCEPTION'], 14, 2), substr($fund['INCEPTION'], 17, 2), substr($fund['INCEPTION'], 5, 2), substr($fund['INCEPTION'], 8, 2), substr($fund['INCEPTION'], 0, 4));
				$fund_name = trim($fund['NAME']);
				$fund_symbol = trim($fund['SYMBOL']);
				$description = trim($fund['DESCRIPTION']);
				$active = $fund['ACTIVE'];
				$fb_fund_primarykey = trim($fund['PRIMARYKEY']);
				if ($first_fund == "" && $active == "1"){
					$first_fund = $fund_num;
				}


	/*
	echo $fund_color;
	echo "|";
	echo $fund_id;
	echo "|";
	echo $seq_id;
	echo "|";
	echo $inception_date;
	echo "|";
	echo $unix_date;
	echo "|";
	echo $fund_name;
	echo "|";
	echo $fund_symbol;
	echo "|";
	echo $description;
	echo "|";
	echo $active;
	echo "|";
	echo $fb_fund_primarykey;
	echo "<br><br>";
	*/

				// Create the fund
				$query =
					"INSERT INTO members_fund (
						fund_id,
						seq_id,
						member_id,
						timestamp,
						inception_date,
						unix_date,
						fund_name,
						fund_symbol,
						description,
						short_fund,
						active,
						version,
						fund_color,
						fb_primarykey
					) VALUES (
						'".$fund_id."',
						".$seq_id.",
						".$member_id.",
						UNIX_TIMESTAMP(),
						'".$inception_date."',
						'".$unix_date."',
						'".addslashes($fund_name)."',
						'".addslashes($fund_symbol)."',
						'".addslashes($description)."',
						0,
						".$active.",
						1,
						'".$fund_color."',
						'".addslashes($fb_fund_primarykey)."'
					)";
				//echo $query."<br>";
				$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

				// Save the fund's settings
				$query =
					"INSERT INTO members_fund_settings (
						fund_id,
						overview_col1,
						overview_col2,
						overview_col3,
						overview_col4,
						fund_color,
						timestamp
					) VALUES (
						'".$fund_id."',
						'fund-price-history~0~0~0|fund-pos-style~0~0~0|fund-info~0~0~0|fund-turnover~0~0~0|fund-profit~0~0~0',
						'fund-pos-sectors~0~0~0|fund-returns-index~0~0~0|fund-recent-returns~0~0~0|fund-alpha-beta~0~0~0|fund-best-worst~0~0~0',
						'',
						'',
						'".$fund_color."',
						UNIX_TIMESTAMP()
					)";
				//echo $query."<br>";
				$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

				// Increment or reset the color counter
				if ($color == 4){
					$color = 0;
				}else{
					$color++;
				}
			}

			// Set their dashboard settings
			if ($first_fund == ""){
				$first_fund = "1";
			}
			$query =
				"INSERT INTO members_settings (
					member_id,
					dash_col1,
					dash_col2,
					dash_4col1,
					dash_4col2,
					dash_4col3,
					dash_4col4,
					ignore_notifications,
					timestamp
				) VALUES (
					".$member_id.",
					'fund-price-history~".$member_id."-".$first_fund."~0~0',
					'tickers~0~0~0|notifications~0~0~0',
					'fund-price-history~".$member_id."-".$first_fund."~0~0',
					'notifications~0~0~0',
					'tickers~0~0~0',
					'',
					'',
					UNIX_TIMESTAMP()
				)";
			//die($query);
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			// Release the result set memory (FrontBase PHP library bug)
			unset($rs_funds);

			// Finally, get their profile info

			// Get their miniscule profile records from FrontBase
	//$fb_primarykey = "X'96326A3839A970ABC0A801E0'";
			$query = "
				SELECT *
				FROM EOUSER.MPREFCATEGORY
				WHERE MANAGERKEY = ".$fb_primarykey.";
			";
			//die($query);
	//echo $query."<br>";
			$rs_profile = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
	//		sleep(1);

			$communication = '';
			$profile = '';
			$contacts = '';
			if (fbsql_num_rows($rs_profile) > 0){
				while ($aProfile = fbsql_fetch_assoc($rs_profile)){
					if ($aProfile['CATEGORYNAME'] == "communication"){
						$communication = addslashes($aProfile['DATA']);
					}
					if ($aProfile['CATEGORYNAME'] == "profile"){
						$profile = addslashes($aProfile['DATA']);
					}
					if ($aProfile['CATEGORYNAME'] == "contacts"){
						$contacts = addslashes($aProfile['DATA']);
					}
				}
			}

	/*
	echo $fb_primarykey;
	echo "|";
	echo $member_id;
	echo "|";
	echo $communication;
	echo "|";
	echo $profile;
	echo "|";
	echo $contacts;
	echo "<br><br>";
	*/

			// Save the profile data
			$query =
				"INSERT INTO temp_member_profile (
					member_id,
					communication,
					profile,
					contacts
				) VALUES (
					'".$member_id."',
					'".$communication."',
					'".$profile."',
					'".$contacts."'
					)";
			//echo $query."<br>";
			$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	//		sleep(1);

			unset($rs_profile);
//		}else{
//			echo $username."<br><br>";
		}
	}

	unset($rs_rows);
//		sleep(1);
}

//	$records = $row[0];

//echo $records;


/*
// Test to make sure the table specified exists in FrontBase - die here if it doesn't
// Also gives a row count for "chunking"
$query = "SELECT COUNT(*) FROM ".$dbUser.".MMANAGER;";
//die($query);
$rs_rows = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Table ".$table." Does Not Exist in DB ".$dbName." in FrontBase - Process Aborted!");
//die("x");

	$row = fbsql_fetch_array($rs_rows);
	$records = $row[0];

echo $records;
*/


// Close database connections
fbsql_close($dbMarketocracy);
mysql_close($linkID);
?>
