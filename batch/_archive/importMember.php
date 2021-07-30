<html><body>
<?php
/*
The purpose of this script is to extract account information from Frontbase and insert into MySQL, effectively creating accounts.
If the URL does not include a username, one is prompted for.  This utility brings in one member at a time.
It must be run through Apache, thus through a browser in order for the FrontBase library to be accessible, unfortunately
*/


// Custom functions

function createDateRangeArray($strDateFrom,$strDateTo,$special="")
{
    // takes two dates formatted as YYYY-MM-DD and creates an
    // inclusive array of the dates between the from and to dates.

    // could test validity of dates here but I'm already doing
    // that in the main script

    $aryRange=array();

    $iDateFrom=mktime(1,0,0,substr($strDateFrom,5,2), substr($strDateFrom,8,2),substr($strDateFrom,0,4));
    $iDateTo=mktime(1,0,0,substr($strDateTo,5,2), substr($strDateTo,8,2),substr($strDateTo,0,4));

	if($special == "dash"){
		if ($iDateTo>=$iDateFrom)
		{
			array_push($aryRange,date('Y-m-d',$iDateFrom)); // first entry
			while ($iDateFrom<$iDateTo)
			{
				$iDateFrom+=86400; // add 24 hours
				array_push($aryRange,date('Y-m-d',$iDateFrom));
			}
		}
	}else{
		if ($iDateTo>=$iDateFrom)
		{
			array_push($aryRange,date('Ymd',$iDateFrom)); // first entry
			while ($iDateFrom<$iDateTo)
			{
				$iDateFrom+=86400; // add 24 hours
				array_push($aryRange,date('Ymd',$iDateFrom));
			}
		}
	}
    return $aryRange;
}

function array_chunk($input, $size, $preserve_keys=false){
	// The PHP function array_chunk() was added in php 4.2 - the fetch server is running 4.1
	// array_chunk() is instrumental in importing fund price history, so here is a hand-made substitute
	@reset( $input );
	$i = $j = 0;
	while(@list($key, $value) = @each($input)){
		if(!(isset($chunks[$i]))){
			$chunks[$i] = array();
		}
		if(count($chunks[$i]) < $size){
			if($preserve_keys){
				$chunks[$i][$key] = $value;
				$j++;
			}else{
				$chunks[$i][] = $value;
			}
		}else{
			$i++;
			if($preserve_keys){
				$chunks[$i][$key] = $value;
				$j++;
			}else{
				$j = 0;
				$chunks[$i][$j] = $value;
			}
		}
	}
	return $chunks;
}

// OK, let's get going...

// Tell me when things go sideways
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Run forever
set_time_limit(1800); // 30 minutes

// Load encryption functions
//require("../includes/crypto.php");

// Connect to MySQL
//require("../includes/dbConnect.php");
require("/var/www/html/includes/dbConnect.php");

// Connect to FrontBase
$dbHost = "192.168.111.141"; // Live (db1)
$dbUser = "EOUSER";
$dbPass = ""; // No password
$dbName = "MARKETOCRACY";
$dbMarketocracy = fbsql_connect($dbHost, $dbUser, $dbPass) or die("Could not connect to FrontBase");
fbsql_select_db($dbName, $dbMarketocracy) or die("Could not select ".$dbName." DB in FrontBase");

// See if we only want the missing funds, assuming the member is already imported
$funds_only = false;
if (isset($_REQUEST['fundsOnly']) && ($_REQUEST['fundsOnly'] == "1" || strtoupper($_REQUEST['fundsOnly']) == "YES" || strtoupper($_REQUEST['fundsOnly']) == "TRUE")){
//if (isset($_REQUEST['fundsOnly']) && ($_REQUEST['fundsOnly'] == "1" || strtoupper($_REQUEST['fundsOnly']) == "YES")){
	$funds_only = true;
}

// See if we want to skip pulling in statistical info - most likely to be true when run automated
$skip_stats = false;
if (isset($_REQUEST['skipStats']) && ($_REQUEST['skipStats'] == "1" || strtoupper($_REQUEST['skipStats']) == "YES" || strtoupper($_REQUEST['skipStats']) == "TRUE")){
//if (isset($_REQUEST['skipStats']) && ($_REQUEST['skipStats'] == "1" || strtoupper($_REQUEST['skipStats']) == "YES")){
	$skip_stats = true;
}

// If there was a username passed, make sure it's ok to import
if (isset($_REQUEST['username']) && $_REQUEST['username'] != ""){

	// Assign passed values
	$username = $_REQUEST['username'];
	$membership = $_REQUEST['membership'];
	// Make them Premium if not otherwise set
	if ($membership == ""){
		$membership = "premium";
	}

	// First see if it's already imported
	$query = "
		SELECT member_id, timestamp, fb_portfoliokey
		FROM members
		WHERE username = '".$username."'
	";
	//die($query);
	$rs_exists = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	$id = mysql_fetch_assoc($rs_exists);
	$member_id = $id['member_id'];
	$fb_portfoliokey = trim($id['fb_portfoliokey']);

	if ($funds_only && mysql_num_rows($rs_exists) == 0){ // Not found, not imported yet
?>
<form action="">
	Member <strong><em>"<?php echo $username; ?>"</em></strong> has not been imported.
	<input id="username" type="hidden" name="username" value="<?php echo $username; ?>">
	<br><br>
	<input type="submit" value="Import Them">
</form>
<?php
			die();
	}
	if ($funds_only || mysql_num_rows($rs_exists) == 0){ // Not found, not imported yet
		// Check with FrontBase to make sure they exist
		$query = "
			SELECT *
			FROM EOUSER.MMANAGER M
			WHERE UPPER(LOGINNAME) = '".strtoupper($username)."';
		";
		//die($query);
		$rs_member = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
		if (fbsql_num_rows($rs_member) == 0){ // Not found, not a real user
?>
<form action="">
	Member <strong><em>"<?php echo $username; ?>"</em></strong> does not exist.
	<br><br>
	<input type="submit" value="Try Another">
</form>
<?php
			die();
		}
	}else{ // Found, they were already imported

		if (!$funds_only){
//			$exists = mysql_fetch_assoc($rs_exists);
?>
<form action="">
	Member <strong><em>"<?php echo $username; ?>"</em></strong> was already imported on <?php echo date('F j, Y @ g:i a', $id['timestamp']); ?>.
	<br><br>
	<input type="submit" value="Try Another">
</form>
<?php
			die();
		}
	}
}else{ // No username passed, present form
?>
<form action="" name="import-form" id="import-form">
	Member to Import (Login Name):
	<input type="text" name="username" value="<?php echo $username; ?>">&nbsp;&nbsp;<input id="fundsOnly" name="fundsOnly" type="checkbox" value="yes">Funds Only&nbsp;&nbsp;<input id="membership" name="membership" type="radio" value="free" checked="checked">Basic (Free)&nbsp;&nbsp;<input id="membership" name="membership" type="radio" value="standard">Plus (Standard)&nbsp;&nbsp;<input id="membership" name="membership" type="radio" value="premium">Pro (Premium)&nbsp;&nbsp;<input id="membership" name="membership" type="radio" value="student">Student&nbsp;|&nbsp;<input id="api" name="api" type="radio" value="api1">API1&nbsp;&nbsp;<input id="api" name="api" type="radio" value="api2" checked="checked">API2
	<br><br>
	<input type="submit" value="Import">
</form>
<script>document.getElementById("import-form").elements["username"].focus();</script>
<?php
	die();
}

// What are we doing?
// This won't work on the Fetch server - ob_flush() didn't exist in < PHP 4.2...can't display status messages as it processes
//echo "Importing Member <strong><em>\"".$username."\"</em></strong> ...<br><br>";
//ob_flush();

// Build a single message for display at the end instead
$message = "Importing".($funds_only ? " funds for" : "")." Member <strong><em>\"".$username."\"</em></strong> ...<br><br>";

// Grab a port to start on randomly
if (!isset($_REQUEST['api']) || ($_REQUEST['api'] == "api2")){
	$start_port = 52100;
	$stop_port = 52499;
}else{
	$start_port = 52000;
	$stop_port = 52099;
}
$port = rand($start_port, $stop_port);

// Let's do this!
$member = fbsql_fetch_array($rs_member);
//print_r($member);die();
// Get their address
if (!$funds_only){
	$phone_day = "";
	$country		= "";
	$country_code	= "";

//	if ($member['HOMEADDRESSKEY'] != ""){









	}
	$query = "
		SELECT *
		FROM EOUSER.MADDRESS
		WHERE PRIMARYKEY = ".trim($member['HOMEADDRESSKEY']).";
	";
	die($query);
	$rs_address = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");
	$aAddress = fbsql_fetch_array($rs_address);
//die("X");
	// Release the result set memory (FrontBase PHP library bug)
	unset($rs_address);

	// Get the phone number, if they have one
	$query = "
		SELECT NUMBER
		FROM EOUSER.MPHONE
		WHERE ADDRESSKEY = ".trim($member['HOMEADDRESSKEY']).";
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
	$username	  		= trim($member['LOGINNAME']);
	//	$encrypted_username	= encrypt(trim($member['LOGINNAME']));
	$name_first			= trim($member['FIRSTNAME']);
	$name_last			= trim($member['LASTNAME']);
	$address			= trim($aAddress['STREETA']);
	$address2			= trim($aAddress['STREETB']);
	$city				= trim($aAddress['CITY']);
	$state				= strtoupper(trim($aAddress['STATE']));
	$zip_code			= strtoupper(trim($aAddress['ZIPCODE']));
	$email				= trim($member['EMAIL']);
	//	$encrypted_email	= encrypt(trim($member['EMAIL']));
	$joined_timestamp	= mktime(substr($member['JOINEDMARKETOCRACY'], 11, 2), substr($member['JOINEDMARKETOCRACY'], 14, 2), substr($member['JOINEDMARKETOCRACY'], 17, 2), substr($member['JOINEDMARKETOCRACY'], 5, 2), substr($member['JOINEDMARKETOCRACY'], 8, 2), substr($member['JOINEDMARKETOCRACY'], 0, 4));
	$fb_primarykey		= trim($member['PRIMARYKEY']);
	$fb_portfoliokey	= trim($member['PORTFOLIOKEY']);
	$fb_lastlogin		= mktime(substr($member['LASTLOGIN'], 11, 2), substr($member['LASTLOGIN'], 14, 2), substr($member['LASTLOGIN'], 17, 2), substr($member['LASTLOGIN'], 5, 2), substr($member['LASTLOGIN'], 8, 2), substr($member['LASTLOGIN'], 0, 4));

	// Release the result set memory (FrontBase PHP library bug)
	unset($rs_member);

	$message .= "Creating membership record.<br>";
	// Create account and write values where they go
	// First, the members table
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

	$message .= "Setting membership flags.<br>";
	// Set the new member's flags
	$query =
		"INSERT INTO members_flags (
			member_id,
			member,
			".$membership."
		) VALUES (
			".$member_id.",
			1,
			1
		)";
	//echo $query."<br>";
	$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

	$message .= "Importing authentication record.<br>";
	// Finally, make sure we have this members password
	$query = "
		SELECT password
		FROM clear_passwords
		WHERE username = '".$username."'
	";
	$rs_password = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

//	if (mysql_num_rows($rs_password) < 1){
	while (mysql_num_rows($rs_password) < 1){

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

		// Now look for it every 15 seconds 10 times
		for ($cnt = 0; $cnt < 9; $cnt++){

			// Wait 15 ticks
			sleep(15);

			$query = "
				SELECT password
				FROM clear_passwords
				WHERE username = '".$username."'
			";
			$rs_password = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

			if (mysql_num_rows($rs_password) > 0){ // Got it!
				break 2;
			}

			if ($cnt > 9){ // It never came...
//				$message .= "Password for <strong><em>\"".$username."\"</em></strong> is missing - please create his authentication record manually.<br>";
				break;
			}
		}
	}
	if (mysql_num_rows($rs_password) > 0){

		$pass = mysql_fetch_assoc($rs_password);
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
	}
}

// Now create all their funds

$message .= "Gathering funds information.<br>";
// Get their funds from FrontBase (Skip short funds and inactive funds)
$query = "
	SELECT *
	FROM EOUSER.MFUND
	WHERE PORTFOLIOKEY = ".$fb_portfoliokey."
	AND KINDKEY = X'70443CA1391E026FC0A8015C'
	AND ACTIVE = 1;
";
//die($query);
$rs_funds = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");

$message .= "Creating funds.<br>";
// Build an array of fund colors
$aColors = array('#39B3D7','#D2322D','#ED9C28','#428BCA','#47A447');
$color = 0;

// Step through the funds
$first_fund = "";
for ($fund_num = 1; $fund_num < fbsql_num_rows($rs_funds) + 1; $fund_num++){
	$fund = fbsql_fetch_assoc($rs_funds);
//print_r($fund);

	if ($funds_only){
		$query = "
			SELECT *
			FROM members_fund
			WHERE fb_primarykey = '".addslashes(trim($fund['PRIMARYKEY']))."'
		";
		//die($query);
		$rs_exists = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		if (mysql_num_rows($rs_exists) > 0){ // Found, already imported
			continue;
		}
		$color = rand(0, 4);
		$query = "
			SELECT MAX(seq_id)
			FROM members_fund
			WHERE member_id = ".$member_id."
		";
		//die($query);
		$rs_max = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
		$max = mysql_fetch_array($rs_max);
		$max_num = $max[0] + 1;
	}

	// Assign values to variables
	$fund_color = $aColors[$color];
	$fund_id = $member_id."-".($funds_only ? $max_num : $fund_num);
	$seq_id = ($funds_only ? $max_num : $fund_num);
	$inception_date = substr($fund['INCEPTION'], 0, 4).substr($fund['INCEPTION'], 5, 2).substr($fund['INCEPTION'], 8, 2);
	$unix_date	= mktime(substr($fund['INCEPTION'], 11, 2), substr($fund['INCEPTION'], 14, 2), substr($fund['INCEPTION'], 17, 2), substr($fund['INCEPTION'], 5, 2), substr($fund['INCEPTION'], 8, 2), substr($fund['INCEPTION'], 0, 4));
	$fund_name = trim($fund['NAME']);
	$fund_symbol = trim($fund['SYMBOL']);
	$description = trim($fund['DESCRIPTION']);
	$active = $fund['ACTIVE'];
	$fb_fund_primarykey = trim($fund['PRIMARYKEY']);
	if ($first_fund == "" && $active == "1"){
		$first_fund = ($funds_only ? $max_num : $fund_num);
	}

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

	// Increment or reset the color counter
	if ($color == 4){
		$color = 0;
	}else{
		$color++;
	}
}

if (!$funds_only){

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

	// Release the result set memory (FrontBase PHP library bug)
	unset($rs_funds);

	$message .= "Creating member profile.<br>";
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

	// Finally, get their profile info
	// Get their miniscule profile records from FrontBase
	$query = "
		SELECT *
		FROM EOUSER.MPREFCATEGORY
		WHERE MANAGERKEY = ".$fb_primarykey."
		AND CATEGORYNAME = 'profile';
	";
	//die($query);
	//echo $query."<br>";
	$rs_profile = fbsql_query($query, $dbMarketocracy) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in FrontBase - Process Aborted!");

	// Parse the profile data and stuff it into the new profile tables
	if (fbsql_num_rows($rs_profile) > 0){
		$profile = fbsql_fetch_assoc($rs_profile);
		$profileInfo = $profile['DATA'];

		// Remove the brackets
		$profileInfo = trim(str_replace(array('{', '}'),array('',''), $profileInfo));

		// Explode the string
		$aItems = explode(';', $profileInfo);

		// Loop through items and remove white space, if value is empty unset it from the array
		foreach($aItems as $key=>$value){

			$aItems[$key] = trim($value);

			// Unset empties
			if($aItems[$key] == '' || $aItems == ' '){
				unset($aItems[$key]);
			}else{
				// Not empty, split the question and answer up
				if($aItems[$key] != ''){
					$aQA = explode('=', $aItems[$key]);
					$aItems[trim($aQA[0])] = str_replace(array('"', '\n'), array('', '<br />'), trim($aQA[1]));
					unset($aItems[$key]);
				}
			}
		}
		$aProfile[$member_id] = $aItems;

		// Clear out the empty array elements
		foreach($aProfile as $key=>$value){
			if(empty($aProfile[$key])){
				unset($aProfile[$key]);
			}
		}

		// Step through each profile (should only be one, actually)
		foreach($aProfile as $member_id=>$aQAs){

			// Step through each Q&A and save them
			foreach($aQAs as $question=>$answer){

				switch($question){
					// What's your age?
					case 'age':

						$yearOfBirth = (date('Y') - $answer).'-01-01';
						$aProfile[$member_id][$question] = $yearOfBirth;

						// Update DOB as best we can
						$query = "
							UPDATE members_profile
							SET DOB = ".$yearOfBirth."
							WHERE member_id = ".$member_id."
						";
						$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

						break;

					// Occupation
					case 'occupation':

						// Update Occupation
						$query = "
							UPDATE members_profile
							SET occupation = '".addslashes($answer)."'
							WHERE member_id = ".$member_id."
						";
						$rs_update = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// What is your investing strategy?
					case 'strategy':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								1,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// Do you have expertise, from your job or personal experience?
					case 'expertise':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								2,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// How long have you been an investor?
					case 'howLong':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								3,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// What attracted you to Marketocracy?
					case 'whyMarketocracy':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								4,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// Do you want to become a fund manager?
					case 'fundManager':

						switch($answer){
							case 'NO': 		$answer = '0';break;
							case 'YES':		$answer = '1';break;
							case 'MAYBE': 	$answer = '2';break;
						}

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								5,
								".$member_id.",
								'".$answer."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// How do you select new companies for your funds?
					case 'howSelect':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								6,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

					// How do you decide to remove a stock from your fund?
					case 'howRemove':

						$query = "
							INSERT INTO members_profile_answers (
								qid,
								member_id,
								answer,
								timestamp
							) VALUES (
								7,
								".$member_id.",
								'".addslashes($answer)."',
								UNIX_TIMESTAMP()
							)
						";
						$rs_insert = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
						break;

				}
			}
		}
	}
	// Release the result set memory (FrontBase PHP library bug)
	unset($rs_profile);
}

if ($first_fund != ""){
	$message .= "Importing fund history.<br>";
	// Get their fund history

	// Get the maxDate
	$query = "
		SELECT maxdate
		FROM members_fund_maxdate
	";
	$rs_maxdate = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
	$date = mysql_fetch_assoc($rs_maxdate);
	$maxDate = $date['maxdate'];

	$message .= "Pricing funds.<br>";
	// Price their fund(s)
	$query = "priceManager|0|".$username;

	// Call on the API Daemon via an EXPECT script
	$cmd = '/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	//echo $cmd."<br>";
	//die();
	exec($cmd);

	// Wait a minute for the pricing to finish
	sleep(60);

	// Get their funds
	$query = "
		SELECT fund_id, fund_symbol, inception_date
		FROM members_fund
		WHERE member_id = ".$member_id."
		AND short_fund = 0
		AND active = 1
	";
	$rs_funds = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

	// Step through them and assign them to an array ($key=fundID, $value=fundSymbol)
	$aFunds = array();
	while ($fund = mysql_fetch_assoc($rs_funds)){
		$aFunds[$fund['fund_id']] = $fund['fund_symbol'];
		// Store their inception date
		$aFundDates[$fund['fund_id']] = $fund['inception_date'];
	}
	//print_r($aFunds);print_r($aFundDates);//die();

	$message .= "Importing daily history for each fund since inception (this could take a while).<br>";
	// Step through the funds array and pull the historical data for each
	foreach ($aFunds as $fund_id => $fund_symbol){
		$message .= "Importing history for ".$fund_symbol." fund.<br>";

		$firstDate = $aFundDates[$fund_id]; // Inception Date

		// If that date is before the maxDate (it should be), then we need to grab everything from that date to maxDate
		if ($firstDate <= $maxDate){

			// Assign dates for priceRun query
			$startDate = substr($firstDate, 0, 4)."-".substr($firstDate, 4, 2)."-".substr($firstDate, 6, 2);
			$endDate = substr($maxDate, 0, 4)."-".substr($maxDate, 4, 2)."-".substr($maxDate, 6, 2);

			// Build array of missing dates
			$queryDates = createDateRangeArray($startDate, $endDate);  // Function found in includes/system-functions.php
	//print_r($queryDates);die();
			// Break it up into 10 day chunks
			$dateChunks = array_chunk($queryDates, 10);

			// Step through the chunks
			for ($cnt = 0; $cnt < count($dateChunks); $cnt++){

				// Grab the first and last date of the chunk
				$startDate = $dateChunks[$cnt][0];
				$endDate = $dateChunks[$cnt][count($dateChunks[$cnt])-1];

				// Build the query for the API
				$query = "priceRun|0|".$username."|".$fund_id."|".$fund_symbol."|".$startDate."|".$endDate;
	//echo $query."\n";

				// Set the port number for the API call
				if ($port == $stop_port){ // Last port #, roll over
					$port = $start_port;
				}else{
					$port++;
				}

				// Call on the API Daemon via an EXPECT script
				$cmd = '/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	//echo $cmd."<br>";
	//die();
				exec($cmd);
			}

			if (!$skip_stats){

				$message .= "Importing statistics for ".$fund_symbol." fund (as of today).<br>";

				// Get current statistical info for each account

				// Define all queries
//				$aQuery[0] = "aggregateStatistics|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
//				$aQuery[1] = "alphaBetaStatistics|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
//				$aQuery[2] = "positionDetail|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
//				$aQuery[3] = "positionStratification|".$username."|".$fund_id."|".$fund_symbol;
//				$aQuery[4] = "stylePositionStratification|".$username."|".$fund_id."|".$fund_symbol;
//				$aQuery[5] = "sectorPositionStratification|".$username."|".$fund_id."|".$fund_symbol;
//				$aQuery[6] = "allPositionInfo|".$username."|".$fund_id."|".$fund_symbol;
//				$aQuery[7] = "livePrice|0|".$username."|".$fund_id."|".$fund_symbol;
//				$aQuery[8] = "tradesForFund|0|".$username."|".$fund_id."|".$fund_symbol;

				$aQuery[0] = "aggregateStatistics|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
				$aQuery[1] = "alphaBetaStatistics|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
				$aQuery[2] = "positionDetail|0|".$username."|".$fund_id."|".$fund_symbol."|".$maxDate;
				$aQuery[3] = "allPositionInfo|".$username."|".$fund_id."|".$fund_symbol;
				$aQuery[4] = "livePrice|0|".$username."|".$fund_id."|".$fund_symbol;
				$aQuery[5] = "tradesForFund|0|".$username."|".$fund_id."|".$fund_symbol;

	//print_r($aQuery);die();

				// Step though and execute each
				foreach ($aQuery as $request => $query){

					// Set the port number for the API call
					if ($port == $stop_port){
						$port = $start_port;
					}else{
						$port++;
					}

					// Call on the API Daemon via an EXPECT script
					$cmd = '/var/www/html/batch/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &';
	//echo $cmd."<br>";
	//die();
					exec($cmd);
				}
			}
		}
	}
/* Moved up top.....
	if (!$funds_only){

		$message .= "Importing authentication record.<br>";
		// Finally, make sure we have this members password
		$query = "
			SELECT password
			FROM clear_passwords
			WHERE username = '".$username."'
		";
		$rs_password = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

		if (mysql_num_rows($rs_password) < 1){

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

			// Now look for it every 15 seconds 10 times
			for ($cnt = 0; $cnt < 9; $cnt++){

				// Wait 15 ticks
				sleep(15);

				$query = "
					SELECT password
					FROM clear_passwords
					WHERE username = '".$username."'
				";
				$rs_password = mysql_query($query, $linkID); //or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

				if (mysql_num_rows($rs_password) > 0){ // Got it!
					break;
				}

				if ($cnt > 9){ // It never came...
					$message .= "Password for <strong><em>\"".$username."\"</em></strong> is missing - please create his authentication record manually.<br>";
					break;
				}
			}
		}
		if (mysql_num_rows($rs_password) > 0){

			$pass = mysql_fetch_assoc($rs_password);
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
		}
	}
*/
}

$message .= "<br>Import of".($funds_only ? " funds for" : "")." <strong><em>\"".$username."\"</em></strong> (member ".$member_id.") complete.";

// Close database connections
fbsql_close($dbMarketocracy);
mysql_close($linkID);

echo $message;

?>
<br><br>
<form action="">
	<input type="submit" value="Import Another">
</form>

</body></html>