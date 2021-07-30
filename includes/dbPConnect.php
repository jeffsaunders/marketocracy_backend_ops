<?php
$dbHost = "192.168.111.211";
$dbUser = "fetcher";
$dbPass = "KfabyZcbE3"; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
//$dbPass = ""; // Pre MySQL 4.1 client - different hash - so password must be set to "MySQL 4.0 compatible" in MySQL
$dbName = "portfolio";
$linkID = mysql_pconnect($dbHost, $dbUser, $dbPass) or die("Could not connect to MySQL");
mysql_select_db($dbName, $linkID) or die("Could not select ".$dbName." DB in MySQL");
?>
