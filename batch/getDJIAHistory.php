<?php
// This commandline batch script grabs "yesterday's" Dow Jones closing price from the ticker feed and writes it into the index history table
// Example:
//	/usr/bin/php /var/www/html/batch/getDJIAHistory.php
// *Note - this will not run within a web browser.

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Get the last closing price
$query = "
	SELECT index_djia
	FROM ".$system_feeds_table."
";
$rs_djia = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
$djia = mysql_fetch_assoc($rs_djia);

$aDJIA = explode('|', $djia['index_djia']);
$indexPrice	= $aDJIA[0];

// Write the history record
$query =	"INSERT INTO ".$index_history_table." (
				`index`,
				date,
				unix_date,
				close,
				timestamp
			) VALUES (
				'^DJI',
				'".date('Y-m-d',strtotime('yesterday'))."',
				".mktime(0, 0, 0, substr(date('Y-m-d',strtotime('yesterday')), 5, 2), substr(date('Y-m-d',strtotime('yesterday')), 8, 2), substr(date('Y-m-d',strtotime('yesterday')), 0, 4)).",
				".(str_replace(",", "", $indexPrice) + 0).",
				UNIX_TIMESTAMP()
			)";

//echo $query;
$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

//echo "Dow Jones history updated.\r\n";
?>