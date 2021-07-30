<?php
$dbHost = "192.168.111.211";
$dbUser = "marketocracy";
$dbPass = "KfabyZcbE3"; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
//$dbUser = "fetcher";
//$dbPass = ""; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbName = "portfolio";
$linkID = mysql_connect($dbHost, $dbUser, $dbPass) or die(mysql_error());
mysql_select_db($dbName, $linkID) or die("Could not select ".$dbName." DB in MySQL");

$dbName = "feed2";
$FlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true) or die(mysql_error());
mysql_select_db($dbName, $FlinkID) or die("Could not select ".$dbName." DB in MySQL");

$dbName = "sites_minc";
$SlinkID = mysql_connect($dbHost, $dbUser, $dbPass, true) or die(mysql_error());
mysql_select_db($dbName, $SlinkID) or die("Could not select ".$dbName." DB in MySQL");
?>
