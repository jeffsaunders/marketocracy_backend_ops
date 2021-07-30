<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Connect to MySQL
//require("../includes/dbPConnect.php");
require("/var/www/html/includes/dbConnect.php");

// Get newest system config values
//require("../includes/getConfig.php");
require("/var/www/html/includes/getConfig.php");

// Truncate table
$query = "
	TRUNCATE TABLE stock_valid_symbols
";
$rs_truncate = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Get new values
//$query = "
//	SELECT 	symbol_feed.Symbol,
//			cusip_feed.Cusip,
//			cusip_feed.Market,
//			stock_feed.Currency,
//			stock_feed.Name,
//			stock_feed.CIK,
//			stock_feed.Valoren,
//			stock_feed.MarketIdentificationCode,
//			stock_feed.CategoryOrIndustry
//	FROM symbol_feed, cusip_feed, stock_feed
//	WHERE symbol_feed.Symbol = cusip_feed.Symbol
//	AND cusip_feed.Symbol = stock_feed.Symbol
//";
//$query = "
//	SELECT 	symbol_feed.Symbol,
//			cusip_feed.Cusip,
//			cusip_feed.Market,
//			stock_feed.Currency,
//			stock_feed.Name,
//			stock_feed.CIK,
//			stock_feed.Valoren,
//			stock_feed.MarketIdentificationCode,
//			stock_feed.CategoryOrIndustry
//	FROM symbol_feed
//	LEFT JOIN cusip_feed ON symbol_feed.Symbol = cusip_feed.Symbol
//	LEFT JOIN stock_feed ON cusip_feed.Symbol = stock_feed.Symbol
//";
$query = "
        SELECT  stock_feed.Symbol,
                        cusip_feed.Cusip,
                        cusip_feed.Market,
                        stock_feed.Currency,
                        stock_feed.Name,
                        stock_feed.CIK,
                        stock_feed.Valoren,
                        stock_feed.MarketIdentificationCode,
                        stock_feed.CategoryOrIndustry
        FROM stock_feed
        LEFT JOIN cusip_feed ON stock_feed.Symbol = cusip_feed.Symbol
";
$rs_feed_data = mysql_query($query, $FlinkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");

// Repopulate the table
while ($row = mysql_fetch_assoc($rs_feed_data)){
	switch($row["MarketIdentificationCode"]) {
		case "XNAS": $exchange = "NASDAQ"; break;
		case "XNYS": $exchange = "NYSE"; break;
		case "XASE": $exchange = "NYSE MKT"; break;
		case "ARCX": $exchange = "NYSE Arca"; break;
		case "OOTC": $exchange = "OTCBB"; break;
		case "XOTC": $exchange = "OTCBB"; break;
		default : continue;
	}

	$query = "
		INSERT INTO stock_valid_symbols (
			symbol,
			company,
			exchange,
			market_id,
			market,
			cusip,
			cik,
			valoren,
			currency,
			industry,
			timestamp
		) VALUES (
			'".$row["Symbol"]."',
			'".addslashes($row["Name"])."',
			'".$exchange."',
			'".$row["MarketIdentificationCode"]."',
			'".$row["Market"]."',
			'".$row["Cusip"]."',
			'".$row["CIK"]."',
			'".$row["Valoren"]."',
			'".$row["Currency"]."',
			'".$row["CategoryOrIndustry"]."',
			UNIX_TIMESTAMP()
		)
	";
//die($query);
	$rs_insert = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
}
?>
