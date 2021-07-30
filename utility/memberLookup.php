<?php
/*
The purpose of this script is to look up members by passed usernames.
Added breakout for those who have not gone through a trial period, those who have but have not completed the transition wizard, and all others - 4/24/17
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
	<title>Member Lookup Tool</title>
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

// Display the form
echo '
<form action="" name="memberLookup" id="memberLookup" method="GET">
	Enter Login Name(s) <span style="font-size:9pt">(Comma Delimited)</span>:
	<input type="text" name="login" size="50" autofocus="autofocus" value="'.$_REQUEST['login'].'">
	<input type="submit">
</form>
';

// If the login name(s) was/were passed, show results
if (isset($_REQUEST['login']) && $_REQUEST['login'] != ""){

	// Wrap all the passed usernames in quotes
	$firstQuotes = "'".$_REQUEST['login']."'";
	$usernames = str_replace(",", "','", $firstQuotes);

	// Look up all the passed usernames
	$query = "
		SELECT *
		FROM members
		WHERE username IN (".$usernames.")
		ORDER BY username ASC
	";
	try{
		$rsMembers = $mLink->prepare($query);
		$rsMembers->execute();
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

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
*/
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

}

// cest' fini

?>
