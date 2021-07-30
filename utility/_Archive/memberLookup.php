<?php
/*
The purpose of this script is to look up members by passed usernames.
*Note - this must be run in a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

// Make sure they passed at least one username
if (!isset($_REQUEST['login']) || $_REQUEST['login'] == ""){
	echo '
You must specify at least one login name to look up (i.e. memberLookup.php?login=mfukui or memberLookup.php?login=mfukui,jeffsaunders,bmccarthy)
	';
	die(); // Just display message and quit
}

// Wrap all the passed usernames in quotes
$firstQuotes = "'".$_REQUEST['login']."'";
$usernames = str_replace(",", "','", $firstQuotes);

// Look up all the passed usernames
$query = "
	SELECT *
	FROM members
	WHERE username IN (".$usernames.")
";
try{
	$rsMembers = $mLink->prepare($query);
	$rsMembers->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Start building the results table
echo '
<table border = "1" cellpadding = "10" cellspacing = "0">
	<tr>
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
		<th>Active</th>
	</tr>
';

// Step through all the results
$count = 1;
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
	$login = date("m/d/Y @ h:i A T", $members['last_login']);
	$active = ($members['active'] = 1 ? "Yes" : "No");

	// Write the row
	echo '
	<tr>
		<td align="right">'.$count.'</td>
		<td align="right">'.$members['member_id'].'</td>
		<td>'.$members['username'].'</td>
		<td>'.$password.'</td>
		<td>'.$name.'</td>
		<td>'.$address.'</td>
		<td>'.$members['email'].'</td>
		<td>'.$members['phone_day'].'</td>
		<td>'.$joined.'</td>
		<td>'.$login.'</td>
		<td>'.$members['fb_primarykey'].'</td>
		<td align="center">'.$active.'</td>
	</tr>
	';
	$count++;
}

// Close the table - we're done!
echo '
</table>
';

?>