<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
die();  // Stop accidental execution.
set_time_limit(1800); // 30 minutes

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

// Help screen if they pass "help" as a parameter
if (isset($_REQUEST['help'])){
	echo
'
Valid Parameters:

duration:		Defaults to 30 days.  Pass it a numeric value equal to the number of days since last login to determine members to update.
help:			Display this message.

example:		getMissingFunds.php "duration=60".  Passing no value(s) assumes defaults.

';
	die(); // Just display message and quit
}

$duration = 30;  // 30 days
if (isset($_REQUEST['duration']) && is_numeric($_REQUEST['duration'])){
	$duration = $_REQUEST['duration'];
}

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get the memberID and last login for everyone who has logged in within the past X days (value passed and defined above) or who is flagged as a Master, Teacher, or Student
if ($duration > 0){
	$query = "
		SELECT m.member_id, m.username, m.last_login, f.promote, f.teacher, f.student
		FROM members m, members_flags f
		WHERE m.member_id = f.member_id
		AND (last_login > :cutoff_date OR f.promote = 1 OR f.teacher = 1 OR f.student = 1)
		GROUP BY member_id
		ORDER BY member_id ASC
	";
}else{  // Do 'em all
	$query = "
		SELECT member_id, username, last_login
		FROM members
		GROUP BY member_id
		ORDER BY member_id ASC
	";
}
try {
	$rsMembers = $mLink->prepare($query);
	$aValues = array(
		':cutoff_date'	=> time() - (86400 * $duration)
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
	$rsMembers->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// What are we doing?
echo "Processing ".$rsMembers->rowCount()." accounts\n";
echo "---------------------------------------------\n";
//sleep(10); // Wait so we can see how many before it scrolls off

while ($members = $rsMembers->fetch(PDO::FETCH_ASSOC)){
//if ($members['username'] == "jeffsaunders"){
//	continue;
//}
//if ($members['member_id'] > 50){
//	break;
//}
	// Who are we processing?
	echo "Processing ".trim($members['username'])." (member ".$members['member_id'].")\n";

	// Use cURL to call the import utility on the fetch server
	$curl = curl_init();
	curl_setopt ($curl, CURLOPT_URL, 'http://192.168.111.212/batch/importMember.php?username='.rawurlencode(trim($members['username'])).'&fundsOnly=yes&skipStats=yes');
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Stay silent, my friend
	curl_exec ($curl);
	curl_close ($curl);
}

// What did we do?
echo "---------------------------------------------\n";
echo "Processed ".$rsMembers->rowCount()." accounts\n";

//die("Done\n");

?>
