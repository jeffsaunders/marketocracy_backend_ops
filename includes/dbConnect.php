<?php
$dbHost = "192.168.111.211";
$dbUser = "marketocracy";
$dbPass = "KfabyZcbE3"; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
//$dbUser = "fetcher";
//$dbPass = ""; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL


// Portfolio
$dbName = "portfolio";
//$linkID = mysql_connect($dbHost, $dbUser, $dbPass) or die(mysql_error());
$try = 0;
if ($try < 5 && ($linkID = mysql_connect($dbHost, $dbUser, $dbPass)) == false) { // Try to connect 5 times
    trigger_error('Could not connect to ...', E_USER_WARNING);
    $try++;
    sleep(2);
}
if (!$linkID){  // Bail if failed
	die(mysql_error());
}
mysql_select_db($dbName, $linkID) or die("Could not select ".$dbName." DB in MySQL");


// Stock price feed
$dbName = "feed2";
//$FlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true) or die(mysql_error());
$try = 0;
if ($try < 5 && ($FlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true)) == false) { // Try to connect 5 times
    trigger_error('Could not connect to ...', E_USER_WARNING);
    $try++;
    sleep(2);
}
if (!$FlinkID){  // Bail if failed
	die(mysql_error());
}
mysql_select_db($dbName, $FlinkID) or die("Could not select ".$dbName." DB in MySQL");


// Public sites data
$dbName = "sites_minc";
//$SlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true) or die(mysql_error());
$try = 0;
if ($try < 5 && ($SlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true)) == false) { // Try to connect 5 times
    trigger_error('Could not connect to ...', E_USER_WARNING);
    $try++;
    sleep(2);
}
if (!$SlinkID){  // Bail if failed
	die(mysql_error());
}
mysql_select_db($dbName, $SlinkID) or die("Could not select ".$dbName." DB in MySQL");
?>
