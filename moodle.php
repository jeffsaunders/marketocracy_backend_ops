<?php

//echo password_hash("Marketocracy1!", PASSWORD_BCRYPT);die();
//exec("curl -c /tmp/cookies -v -L --user admin:Hawaii50! http://moodle.marketocracy.com");
//header("Location: http://moodle.marketocracy.com");


$username = stripslashes($_GET['username']);
//$username = "admin";
$password = stripslashes($_GET['password']);
//$password = "Hawaii50!";

# http://192.168.111.215/moodle.php?username=jeffsaunders&password=n0thing!

/*
<form action="http://moodle.marketocracy.com/login/index.php" method="post" name="login" id="form">

<p><input type="text" name="username" value="<?php echo $username ?>">

<p><input type="hidden" name="password" value="<?php echo $password ?>">

<script language="JavaScript">

function Validate()

{

document.login.submit();

}

Validate();

</script>

</form>';
*/

/*
The purpose of this script is to log the member into Moodle seamlessly.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

//Start session
session_start();
$_SESSION['base_url'] = "portfolio.marketocracy.com/";

// Load debug functions
require("/var/www/html/".$_SESSION['base_url']."web/includes/system-debug-functions.php");

// Load encryption functions
require("/var/www/html/".$_SESSION['base_url']."secure/crypto.php");

// Connect to database
require("/var/www/html/".$_SESSION['base_url']."scripts/dbConnect.php");

//Get site functions
require("/var/www/html/".$_SESSION['base_url']."scripts/system-functions.php");

//Get global settings
require("/var/www/html/".$_SESSION['base_url']."web/includes/system-global.php");


// Assign member's username
$username = $_SESSION['username'];

// Get member's password
$query = "
	SELECT password
	FROM system_authentication
	WHERE member_id = ".$_SESSION['member_id']."
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

echo $username."/".$password;die();


?>

<form action="http://moodle.marketocracy.com/login/index.php" method="post" name="login" id="form">
<input type="hidden" name="username" value="<?php echo $username ?>">
<input type="hidden" name="password" value="<?php echo $password ?>">

<script language="JavaScript">
function Validate(){
	document.login.submit();
}
Validate();
</script>

</form>';
