<?php
/*
The purpose of this script is to find members with no funds and break them out by various groups (to determine likely reason they have no funds set up).
*Note - this must be run in a web browser.
*/

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set up the page
echo '
<head>
	<title>Members with No Funds</title>
</head>
';

// Run long enough
set_time_limit(900); // 15 minutes

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

/*
// Get all members without any funds at all and stuff them into a temp table

// First, drop any old temp table left over from before
$query = "
	DROP TABLE IF EXISTS temp_members
";
try{
	$rsDropTable = $mLink->prepare($query);
	$rsDropTable->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Now build a new one
$query = "
	CREATE TABLE temp_members
	AS
		(SELECT *
		 FROM members
		 WHERE active = 1
		 AND NOT EXISTS
			(SELECT member_id
			 FROM members_fund
			 WHERE member_id = members.member_id
			)
		 ORDER BY member_id ASC
		)
";
try{
	$rsMembersCopy = $mLink->prepare($query);
	$rsMembersCopy->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// OK, let's eliminate "staff" members
$query = "
	DELETE FROM temp_members
	WHERE member_id IN
		(SELECT member_id
		 FROM members_flags
		 WHERE member_id = temp_members.member_id
		 AND staff = 1
		)
";
try{
	$rsDeleteStaff = $mLink->prepare($query);
	$rsDeleteStaff->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
*/
// Now let's see how many members have no funds
$query = "
	SELECT count(*)
	FROM temp_members
";
try{
	$rsCountTotal = $mLink->prepare($query);
	$rsCountTotal->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$totalMembersCount = $rsCountTotal->fetch(PDO::FETCH_NUM);
$totalMembers = $totalMembersCount[0];

//Let's grab a clean result set form the temp table and start interrogating it
$query = "
	SELECT *
	FROM temp_members
	ORDER BY member_id
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Set up some counters
$imported 			= 0; // # imported
$inTrial 			= 0; // # in trial
$loginNever 		= 0; // # who've never logged in
$lastLogin90 		= 0; // # last logged in more than 90 days ago
$lastLogin60 		= 0; // # last logged in more than 60 but fewer than 90 days ago
$lastLogin30 		= 0; // # last logged in more than 30 but fewer than 60 days ago
$lastLogin0 		= 0; // # last logged in fewer than 30 days ago
$freeTrial			= 0; // # with active free trial membership
$freeBasicMember	= 0; // # with free Basic membership
$plusMember 		= 0; // # with Plus membership
$proMember 			= 0; // # with Pro membership
$legacyProMember 	= 0; // # with Legacy Pro membership
$managerProMember 	= 0; // # with free Manager Pro membership
$compProMember 		= 0; // # with free/complimentary Pro membership
$paidBasic1Fund 	= 0; // # of Paid Basic members with 1 fund
$paidBasic2Fund 	= 0; // # of Paid Basic members with 2 funds
$paidBasic3Fund 	= 0; // # of Paid Basic members with 3 funds
$noMembership		= 0; // # with no membership record at all

// And some arrays
$aImported = array();

while($members = $rsMembers->fetch(PDO::FETCH_ASSOC)){

	// Get authentication information
	$query = "
		SELECT *
		FROM system_authentication
		WHERE member_id = :member_id
		ORDER BY timestamp DESC
		LIMIT 1
	";
	try{
		$rsAuthentication = $mLink->prepare($query);
		$aValues = array(
			':member_id'       => $members['member_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsAuthentication->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$auth = $rsAuthentication->fetch(PDO::FETCH_ASSOC);

	// Increment imported counter
//	$imported += ($auth['imported'] == 0 ? 0 : 1);

	if ($auth['imported'] == 1){
		$imported++;
		array_push($aImported, $auth['member_id']);
	}


	// Increment trial counter
	if ($auth['trial_start'] != "" && $auth['trial_start'] != 0){
		if ($auth['trial_end'] == "" || $auth['trial_end'] == 0){
			$inTrial++;
		}
	}

	// Determine when they last logged in and increment the appropriate counter
	$lastLoginTimestamp = $auth['last_login'];
	if ($lastLoginTimestamp == null || $lastLoginTimestamp == 0){
		$loginNever++;
	}elseif (strtotime("90 days ago") > $lastLoginTimestamp){
		$lastLogin90++;
	}elseif (strtotime("60 days ago") > $lastLoginTimestamp){
		$lastLogin60++;
	}elseif (strtotime("30 days ago") > $lastLoginTimestamp){
		$lastLogin30++;
	}else{
		$lastLogin0++;
	}

	// Get subscription information
	$query = "
		SELECT *
		FROM members_subscriptions
		WHERE member_id = :member_id
		AND active = 1
		ORDER BY product_id ASC
	";
	try{
		$rsSubscriptions = $mLink->prepare($query);
		$aValues = array(
			':member_id'       => $members['member_id']
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;die();
		$rsSubscriptions->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	while($sub = $rsSubscriptions->fetch(PDO::FETCH_ASSOC)){

		if ($sub['product_id'] == 0){
			$freeTrial++;
		}elseif ($sub['product_id'] == 1){
			$freeBasicMember++;
		}elseif ($sub['product_id'] == 2){
			$plusMember++;
		}elseif ($sub['product_id'] == 3){
			$proMember++;
		}elseif ($sub['product_id'] == 4){
			$legacyProMember++;
		}elseif ($sub['product_id'] == 10){
			$managerProMember++;
		}elseif ($sub['product_id'] == 11){
			$compProMember++;
		}elseif ($sub['product_id'] == 101){
			$paidBasic1Fund++;
		}elseif ($sub['product_id'] == 102){
			$paidBasic2Fund++;
		}elseif ($sub['product_id'] == 103){
			$paidBasic3Fund++;
		}else{
			$noMembership++;
		}

	}


//echo "Member ".$members['member_id'].": Imported = ".($auth['imported'] == 0 ? "False" : "True").", In Trial = ".       ."<br>";





}

echo "Total Members: ".$totalMembers."<br><br>";

echo "Imported: ".$imported."<br><br>";
print_r($aImported);

echo "In Trial: ".$inTrial."<br><br>";

echo "Login Never: ".$loginNever."<br>";
echo "Login 90: ".$lastLogin90."<br>";
echo "Login 60: ".$lastLogin60."<br>";
echo "Login 30: ".$lastLogin30."<br>";
echo "Login 0: ".$lastLogin0."<br><br>";

echo "Free Trial Memberships: ".$freeTrial."<br>";
echo "Free Basic Memberships: ".$freeBasicMember."<br>";
echo "Plus Memberships: ".$plusMember."<br>";
echo "Pro Memberships: ".$proMember."<br>";
echo "Legacy Pro Memberships: ".$legacyProMember."<br>";
echo "Manager Pro Memberships: ".$managerProMember."<br>";
echo "Complimentary Pro Memberships: ".$compProMember."<br>";
echo "Paid Basic Memberships with One Fund: ".$paidBasic1Fund."<br>";
echo "Paid Basic Memberships with Two Funds: ".$paidBasic2Fund."<br>";
echo "Paid Basic Memberships with Three Funds: ".$paidBasic3Fund."<br>";
echo "No Active Memberships Record: ".$noMembership."<br>";


/*
// Finally, clean up after yourself...
$query = "
	DROP TABLE temp_members
";
try{
	$rsDropTable = $mLink->prepare($query);
	$rsDropTable->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
*/












/*
		SELECT m.*, a.*
		FROM temp_members m
		INNER JOIN system_authentication a
			ON a.member_id = m.member_id
			INNER JOIN
				(
					SELECT member_id, timestamp
					FROM system_authentication
					WHERE
					ORDER BY timestamp DESC
					LIMIT 1
				) c ON c.member_id = m.member_id




	// Set up some vars
	$aTransWiz = [];
	$aNoTrial = [];
	$aActive = [];

	// Step through all the results
	//$count = 1;
	while($members = $rsMembers->fetch(PDO::FETCH_ASSOC)){

		// Get their passwords
		$query = "
			SELECT password
			FROM system_authentication
			WHERE member_id = ".$members['member_id']."
			ORDER BY timestamp DESC
		    LIMIT 1
		";
		try{
			$rsPassword = $mLink->prepare($query);
			$rsPassword->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Decrypt their password
		$pass = $rsPassword->fetch(PDO::FETCH_ASSOC);
		$encryptedPassword = $pass['password'];
		$password = decrypt($encryptedPassword);

		// Assign the rest of the massaged values
		$name = $members['name_first']." ".$members['name_last'];
		$address = $members['address'].(!empty($members['city']) ? ", ".$members['city'] : "").(!empty($members['state']) ? ", ".$members['state'] : "").(!empty($members['zip_code']) ? ", ".$members['zip_code'] : "").((!empty($members['country']) && $members['country'] != "United States") ? ", ".$members['country'] : "");
		$joined = date("m/d/Y", $members['joined_timestamp']);
		if (date("Y", $members['last_login']) == 1969){
			$login = "NEVER";
		}else{
			$login = date("m/d/Y @ h:i A T", $members['last_login']);
		}
		$active = ($members['active'] = 1 ? "Yes" : "No");

		// Let's see if they ever even entered a trial period
		$query = "
			SELECT COUNT(*)
			FROM members_subscriptions
			WHERE member_id = ".$members['member_id']."
			AND start_timestamp IS NULL
		";
		try{
			$rsTrial = $mLink->prepare($query);
			$rsTrial->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$noTrial = $rsTrial->fetchColumn(); // 1 (true) if they are yet to even go through a trial, 0 (false) if not.

		// Now let's see if they are queued up for the transition wizard
		$query = "
			SELECT COUNT(*)
			FROM members_subscriptions
			WHERE member_id = ".$members['member_id']."
			AND product_id IN (0,99)
			AND trans_wiz IS NULL
			AND active = 1
		";
		try{
			$rsTransWiz = $mLink->prepare($query);
			$rsTransWiz->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$transWiz = $rsTransWiz->fetchColumn();  // 1 (true) if they are awaiting the transition wizard, 0 (false) if not.

		// Let's see how many funds they have - having only 1 fund will not trigger the transition wizard
/* Turns out Marty only wants to know if HE would be forced into the transition wizard (as them), so this check is unnecessary
		if ($transWiz == 1){
			$query = "
				SELECT COUNT(*)
				FROM members_fund
				WHERE member_id = ".$members['member_id']."
				AND active = 1
			";
			try{
				$rsFundCount = $mLink->prepare($query);
				$rsFundCount->execute();
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
			$fundCount = $rsFundCount->fetchColumn();  // How many funds do they have?

			$transWiz = ($fundCount > 1 ? 1 : 0);

		}
/
		// Ok, let's assign them to the proper array
		if ($noTrial == 1){
			$aNoTrialTemp['member_id'] = $members['member_id'];
			$aNoTrialTemp['username'] = $members['username'];
			$aNoTrialTemp['password'] = $password;
			$aNoTrialTemp['name'] = $name;
			$aNoTrialTemp['address'] = $address;
			$aNoTrialTemp['email'] = $members['email'];
			$aNoTrialTemp['phone_day'] = $members['phone_day'];
			$aNoTrialTemp['joined'] = $joined;
			$aNoTrialTemp['login'] = $login;
			$aNoTrialTemp['fb_primarykey'] = $members['fb_primarykey'];
			$aNoTrialTemp['active'] = $active;
			$aNoTrial[] = $aNoTrialTemp;
		}elseif ($transWiz == 1){
			$aTransWizTemp['member_id'] = $members['member_id'];
			$aTransWizTemp['username'] = $members['username'];
			$aTransWizTemp['password'] = $password;
			$aTransWizTemp['name'] = $name;
			$aTransWizTemp['address'] = $address;
			$aTransWizTemp['email'] = $members['email'];
			$aTransWizTemp['phone_day'] = $members['phone_day'];
			$aTransWizTemp['joined'] = $joined;
			$aTransWizTemp['login'] = $login;
			$aTransWizTemp['fb_primarykey'] = $members['fb_primarykey'];
			$aTransWizTemp['active'] = $active;
			$aTransWiz[] = $aTransWizTemp;
		}else{
			$aActiveTemp['member_id'] = $members['member_id'];
			$aActiveTemp['username'] = $members['username'];
			$aActiveTemp['password'] = $password;
			$aActiveTemp['name'] = $name;
			$aActiveTemp['address'] = $address;
			$aActiveTemp['email'] = $members['email'];
			$aActiveTemp['phone_day'] = $members['phone_day'];
			$aActiveTemp['joined'] = $joined;
			$aActiveTemp['login'] = $login;
			$aActiveTemp['fb_primarykey'] = $members['fb_primarykey'];
			$aActiveTemp['active'] = $active;
			$aActive[] = $aActiveTemp;
		}

	}

	// OK, got 'em all...
	// Build table of all inactive members
	echo '
	<h2>Inactive Members (Haven\'t Logged In Since Before 12/01/16)</h2>
	<table border = "1" cellpadding = "5" cellspacing = "0">
		<tr style="background-color:#909090;">
			<th>Count</th>
			<th>Member ID</th>
			<th>Login Name</th>
			<th>Password</th>
			<th>Name</th>
			<th>Address</th>
			<th>Email</th>
			<th>Phone</th>
			<th>Joined</th>
			<th>Last Login</th>
			<th>Manager Key</th>
			<th>Enabled</th>
		</tr>
	';
	if (empty($aNoTrial)){
		echo '
		<tr>
			<td colspan="12"><strong>NONE</strong></td>
		</tr>
		';
	}else{
		foreach($aNoTrial as $key=>$member){
			$count = $key + 1;
			echo '
		<tr>
			<td align="right">'.$count.'</td>
			<td align="right">'.$member['member_id'].'</td>
			<td>'.$member['username'].'</td>
			<td>'.$member['password'].'</td>
			<td>'.$member['name'].'</td>
			<td>'.$member['address'].'</td>
			<td>'.$member['email'].'</td>
			<td>'.$member['phone_day'].'</td>
			<td>'.$member['joined'].'</td>
			<td><strong>'.$member['login'].'</strong></td>
			<td>'.$member['fb_primarykey'].'</td>
			<td align="center">'.$member['active'].'</td>
		</tr>
			';
		}
	}
	echo '
	</table>
	<br>
	';

	// Build table of all members queued for the transition wizard
	echo '
	<h2>Members Queued For Transition Wizard</h2>
	<table border = "1" cellpadding = "5" cellspacing = "0">
		<tr style="background-color:#909090;">
			<th>Count</th>
			<th>Member ID</th>
			<th>Login Name</th>
			<th>Password</th>
			<th>Name</th>
			<th>Address</th>
			<th>Email</th>
			<th>Phone</th>
			<th>Joined</th>
			<th>Last Login</th>
			<th>Manager Key</th>
			<th>Enabled</th>
		</tr>
	';
	if (empty($aTransWiz)){
		echo '
		<tr>
			<td colspan="12"><strong>NONE</strong></td>
		</tr>
		';
	}else{
		foreach($aTransWiz as $key=>$member){
			$count = $key + 1;
			echo '
		<tr>
			<td align="right">'.$count.'</td>
			<td align="right">'.$member['member_id'].'</td>
			<td>'.$member['username'].'</td>
			<td>'.$member['password'].'</td>
			<td>'.$member['name'].'</td>
			<td>'.$member['address'].'</td>
			<td>'.$member['email'].'</td>
			<td>'.$member['phone_day'].'</td>
			<td>'.$member['joined'].'</td>
			<td><strong>'.$member['login'].'</strong></td>
			<td>'.$member['fb_primarykey'].'</td>
			<td align="center">'.$member['active'].'</td>
		</tr>
			';
		}
	}
	echo '
	</table>
	<br>
	';

	// Build table of all active members (the rest)
	echo '
	<h2>Active Members</h2>
	<table border = "1" cellpadding = "5" cellspacing = "0">
		<tr style="background-color:#909090;">
			<th>Count</th>
			<th>Member ID</th>
			<th>Login Name</th>
			<th>Password</th>
			<th>Name</th>
			<th>Address</th>
			<th>Email</th>
			<th>Phone</th>
			<th>Joined</th>
			<th>Last Login</th>
			<th>Manager Key</th>
			<th>Enabled</th>
		</tr>
	';
	if (empty($aActive)){
		echo '
		<tr>
			<td colspan="12"><strong>NONE</strong></td>
		</tr>
		';
	}else{
		foreach($aActive as $key=>$member){
			$count = $key + 1;
			echo '
		<tr>
			<td align="right">'.$count.'</td>
			<td align="right">'.$member['member_id'].'</td>
			<td>'.$member['username'].'</td>
			<td>'.$member['password'].'</td>
			<td>'.$member['name'].'</td>
			<td>'.$member['address'].'</td>
			<td>'.$member['email'].'</td>
			<td>'.$member['phone_day'].'</td>
			<td>'.$member['joined'].'</td>
			<td><strong>'.$member['login'].'</strong></td>
			<td>'.$member['fb_primarykey'].'</td>
			<td align="center">'.$member['active'].'</td>
		</tr>
			';
		}
	}
	echo '
	</table>
	<br><br>
	';

//}

// cest' fini
*/
?>