<?php
// This commandline batch script grabs today's closing price from each of the tracked indexes and writes it into the index history table
// Example:
//	/usr/bin/php /var/www/html/batch/getIndexClosing.php
// *Note - this will not run within a web browser.

// Connect to MySQL
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
require("/var/www/html/includes/getConfig.php");

// Get the last closing price
$query = "
	SELECT *
	FROM ".$system_feeds_table."
";
$rs_closing = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
$closing = mysql_fetch_assoc($rs_closing);

//$aLabels = array("sp500","nasdaq","djia","nyse","rut","w5000","sp500TR"); //S&P, NASDAQ, Dow Jones, NYSE, Russell 2000, Wilshire 5000, S&P Total Return
//$aIndicies = array("^GSPC","^IXIC","^INDU","^NYA","^RUT","^W5000","^SP500TR"); //S&P, NASDAQ, Dow Jones, NYSE, Russell 2000, Wilshire 5000, S&P Total Return
$aLabels = array("sp500","nasdaq","djia","nyse","rut","rua","sp500TR"); //S&P, NASDAQ, Dow Jones, NYSE, Russell 2000, Russle 3000, S&P Total Return
$aIndicies = array("^GSPC","^IXIC","^INDU","^NYA","^RUT","^RUA","^SP500TR"); //S&P, NASDAQ, Dow Jones, NYSE, Russell 2000, Russell 3000, S&P Total Return
$aClosing = array();

// Loop through all the indexes
for ($indexCnt = 0; $indexCnt < count($aLabels); $indexCnt++){
	$index = $aLabels[$indexCnt];

	$column = "index_".$index;

	$aValue = explode('|', $closing[$column]);
	$aClosing[$indexCnt] = $aValue[0];

}

//print_r($aLabels);
//print_r($aIndicies);
//print_r($aClosing);
//die();

// Write the history record
$query =	"INSERT INTO ".$index_history_table." (
				`index`,
				date,
				unix_date,
				close,
				timestamp
			) VALUES
";

// Loop through all the arrays
for ($arrayCnt = 0; $arrayCnt < count($aLabels); $arrayCnt++){

//	$query .= "(
//				'".$aIndicies[$arrayCnt]."',
//				'".date('Y-m-d',strtotime('yesterday'))."',
//				".mktime(0, 0, 0, substr(date('Y-m-d',strtotime('yesterday')), 5, 2), substr(date('Y-m-d',strtotime('yesterday')), 8, 2), substr(date('Y-m-d',strtotime('yesterday')), 0, 4)).",
//				".(str_replace(",", "", $aClosing[$arrayCnt]) + 0).",
//				UNIX_TIMESTAMP()
//			),";
	$query .= "(
				'".$aIndicies[$arrayCnt]."',
				'".date('Y-m-d')."',
				".mktime(0, 0, 0, substr(date('Y-m-d'), 5, 2), substr(date('Y-m-d'), 8, 2), substr(date('Y-m-d'), 0, 4)).",
				".(str_replace(",", "", $aClosing[$arrayCnt]) + 0).",
				UNIX_TIMESTAMP()
			),";
}

// Pop the trailing "," off
$query = substr($query, 0, -1);

//echo $query;
//die();
$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

//echo "Index closing price history updated.\r\n";
?>
