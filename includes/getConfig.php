<?php

// What time is it?
date_default_timezone_set('America/New_York');

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
//echo $query;
$rs_config = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Create variables from the stored settings and assign the stored values to them
while ($config = mysql_fetch_assoc($rs_config)){
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
$rs_config = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Create variables from the stored settings and assign the stored values to them
while ($config = mysql_fetch_assoc($rs_config)){
	$var = trim($config['setting']); // Just in case there are spaces before or after the value
	$$var = trim($config['value']);  // Create the var based on the "setting" value, and assign the actual "value" value to it
//echo $var." -> ".$$var."\r\n";
}

?>