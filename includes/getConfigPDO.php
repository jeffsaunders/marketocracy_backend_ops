<?php

// What time is it?
date_default_timezone_set('America/New_York');


//-----
/*
// Dump ALL existing variables and their values
function dump_vars($mixed=null){
	ob_start();
//	var_dump($mixed);
	print_r($mixed);
	$sVars = ob_get_contents();
	ob_end_clean();
	return $sVars;
}

//-----
// Dump array elements variables and their values
function dump_array($mixed=null){
	ob_start();
	$sElem = "<pre>";
	print_r($mixed);
	$sElem .= htmlentities(ob_get_contents());
	$sElem .= "</pre>";
	ob_end_clean();
	return $sElem;
}
*/

// Get newest system config values
// This query grabs the newest version of each setting, but since we are just updating the values there will only be one
//$query = "
//	SELECT *
//	FROM system_config conf
//	INNER JOIN (
//		SELECT max(uid) AS uid, setting
//		FROM system_config
//		GROUP BY setting
//	) dup ON dup.setting = conf.setting
//	WHERE conf.uid = dup.uid
//";
$query = "
	SELECT *
	FROM system_config
";
try {
	$rsConfig = $mLink->prepare($query);
//	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
	$rsConfig->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Create variables from the stored settings and assign the stored values to them
while ($config = $rsConfig->fetch(PDO::FETCH_ASSOC)){
	$var = trim($config['setting']); // Just in case there are spaces before or after the value
	$$var = trim($config['value']);  // Create the var based on the "setting" value, and assign the actual "value" value to it
//echo $var." -> ".$$var."\r\n";
}

//...and database table definitions
// This query grabs the newest version of each setting, but since we are just updating the values there will only be one
//$query = "
//	SELECT *
//	FROM system_config_database conf
//	INNER JOIN (
//		SELECT max(uid) AS uid, setting
//		FROM system_config_database
//		GROUP BY setting
//	) dup ON dup.setting = conf.setting
//	WHERE conf.uid = dup.uid
//";
$query = "
	SELECT *
	FROM system_config_database
";
try {
	$rsConfig = $mLink->prepare($query);
//	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//die($preparedQuery);
	$rsConfig->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Create variables from the stored settings and assign the stored values to them
while ($config = $rsConfig->fetch(PDO::FETCH_ASSOC)){
	$var = trim($config['setting']); // Just in case there are spaces before or after the value
	$$var = trim($config['value']);  // Create the var based on the "setting" value, and assign the actual "value" value to it
//echo $var." -> ".$$var."\r\n";
}

?>