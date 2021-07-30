<?php
/*
The purpose of this script is to encrypt a passed string using the same encryption routine we use for passwords, etc.
*Note - this must be run in a web browser.
*/

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Decrypt functions
function decrypt($value, $key='SGFwcHkgQmlydGhkYXkgUmFjaGVsIQ=='){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$decryptedValue = mdecrypt_generic($td, base64_decode($value));
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return $decryptedValue;
}

function double_decrypt($value, $key='fhPXaYlnraw4aN6mOVOfOXPdEtVQGZml'){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$decryptedValue = mdecrypt_generic($td, base64_decode($value));
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return $decryptedValue;
}

echo '
<!DOCTYPE HTML>

<html>
<head>
	<title>Decrypter</title>
</head>

<style>
	a:link {color:#000000; text-decoration:none;}
	a:visited {color:#000000; text-decoration:none;}
	a:hover {color:#FF0000; text-decoration:underline;}
	a:active {color:#000000; text-decoration:none;}
</style>

<body>

<form action="" name="decrypt" id="decrypt">
	String to Decrypt:
	<input type="text" name="string">
	<input type="submit">
	<input type="checkbox" name="double">Double Decrypt
</form>
';

// Display results if string is passed
if ($_REQUEST['string']){
	if ($_REQUEST['double'] != true){
		$output = decrypt($_REQUEST['string']);
	}else{
		$output = double_decrypt($_REQUEST['string']);
	}
	echo '
<h3 style="font-weight:normal;">'.($_REQUEST['double'] == true ? 'Double ': '').'Decrypted String for <em>&quot;'.$_REQUEST['string'].'&quot;</em> &ndash;&raquo; <strong>'.$output.'</strong></h3>
	';
}else{
	echo '
<br>
	';
}

echo '
<a href="/utility/encrypt.php">Switch to Encrypt</a>

</body>
</html>
';

?>
