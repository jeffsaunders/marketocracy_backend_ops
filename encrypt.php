<?php
// Takes an input string and returns an encrypted version
// The primary purpose for this is to allow the Fetch server to ask for the info (via HTTP) as it cannot perform the conversion itself
if ($_REQUEST['string']){
	// Create encrypt function for password comparison
	function encrypt($value, $key='SGFwcHkgQmlydGhkYXkgUmFjaGVsIQ=='){
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
	$output = encrypt($_REQUEST['string']);
	echo $output;
}
?>