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

// Encryption functions
function encrypt($value, $key='<key>'){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$encryptedValue = mcrypt_generic($td, $value);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return base64_encode( $encryptedValue );
}

function double_encrypt($value, $key='<doubleKey>'){
	if(!$value || !$key){
		return false;
	}
	$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size( $td ), MCRYPT_RAND);
	mcrypt_generic_init($td, $key, $iv);
	$encryptedValue = mcrypt_generic($td, $value);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	return base64_encode( $encryptedValue );
}

echo '
<!DOCTYPE HTML>

<html>
<head>
	<title>Encrypter</title>
</head>

<style>
	a:link {color:#000000; text-decoration:none;}
	a:visited {color:#000000; text-decoration:none;}
	a:hover {color:#FF0000; text-decoration:underline;}
	a:active {color:#000000; text-decoration:none;}
</style>

<body>

<form action="" name="encrypt" id="encrypt">
	String to Encrypt:
	<input type="text" name="string">
	<input type="submit">
	<input type="checkbox" name="double">Double Encrypt
</form>
';

// Display results if string is passed
if ($_REQUEST['string']){
	if ($_REQUEST['double'] != true){
		$output = encrypt($_REQUEST['string']);
	}else{
		$output = double_encrypt($_REQUEST['string']);
	}
	echo '
<h3 style="font-weight:normal;">'.($_REQUEST['double'] == true ? 'Double ': '').'Encrypted String for <em>&quot;'.$_REQUEST['string'].'&quot;</em> &ndash;&raquo; <strong>'.$output.'</strong></h3>
	';
}else{
	echo '
<br>
	';
}

echo '
<a href="/utility/decrypt.php">Switch to Decrypt</a>

</body>
</html>
';

?>
