<?php
// Some convenient functions

//-----
// Is passed value odd?
function isOdd($num){
	return (is_numeric($num)&($num&1));
}

// Is passed value even?
function isEven($num){
	return (is_numeric($num)&(!($num&1)));
}

//-----
// Determine if day is a weekday
// Pass time
function isWeekday($timestamp) {
	return (date('N', $timestamp) < 6); // ISO DoW (7 = Sunday)
}

//-----
// Determine if day is a market holiday
// Pass time & DB link
// Returns false if not, "Y" if it is, "E" if it's an early closing day
function isMarketHoliday($timestamp, $linkID) {
//	if (isset($_SESSION['market_holiday'])){
//		return $_SESSION['market_holiday'];
//	}

	$query = "
		SELECT *
		FROM system_holidays
		WHERE date = ".date('Y-m-d', $timestamp)."
	";
	$rsHoliday = mysql_query($query, $linkID) or die ("ERROR - Query '".$query."' Failed for DB ".$dbName." in MySQL - Process Aborted!");
//	try {
//		$rsHoliday = $mLink->prepare($query);
//		$aValues = array(
//			':date'		=> date('Y-m-d', $timestamp)
//		);
//		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
//		//return $preparedQuery;
//		$rsHoliday->execute($aValues);
//	}
//	catch(PDOException $error){
//		// Log any error
//		file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars(//))."\r", FILE_APPEND);
//	}

	if (mysql_num_rows($rsHoliday) > 0){  // It's a holiday
		$holiday = mysql_fetch_assoc($rsHoliday);
//		$_SESSION['market_holiday'] = $holiday['closed'];
//		$_SESSION['market_holiday_occasion'] = $holiday['occasion'];
		return $holiday['closed']; // "Y" if it is a holiday, "E" if it closes early
	}
//	if ($rsHoliday->rowCount() > 0){  // It's a holiday
//		$holiday = $rsHoliday->fetch(PDO::FETCH_ASSOC);
//		$_SESSION['market_holiday'] = $holiday['closed'];
//		$_SESSION['market_holiday_occasion'] = $holiday['occasion'];
//		return $holiday['closed']; // "Y" if it is a holiday, "E" if it closes early
//	}
//	$_SESSION['market_holiday'] = false;
	return false;
}

//-----
// Determine if market is open
// Pass time, DB link (for holiday lookup), and whether to pad start and end times
// Returns true or false
function isMarketOpen($timestamp, $linkID, $fudge='none') {
	// Is it a weekday?
	if (isWeekday($timestamp)){
		switch($fudge){
			case 'none': // ACTUAL market hours (9:30 to 4:00 ET, 1:00 if it's an early close day)
				$begin = "9:30 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:01 PM" : "4:01 PM");
				break;

			case 'before':  // Start 30 minutes early, end on time
				$begin = "9:00 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:01 PM" : "4:01 PM");
				break;

			case 'after': // Start on time, end 30 minutes late
				$begin = "9:30 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:30 PM" : "4:30 PM");
				break;

			case 'both':  // Start 30 minutes early, end 30 minutes late
				$begin = "9:00 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:30 PM" : "4:30 PM");
				break;

			default: // Use actual market hours if not properly specified
				$begin = "9:30 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:01 PM" : "4:01 PM");
		}
		if (isMarketHoliday($timestamp, $linkID) == "Y"){  // Closed all day
			return false;
		}else{ // Open today
			if ($timestamp > strtotime(date('j-n-Y', $timestamp).' '.$begin.' America/New_York') && $timestamp < strtotime(date('j-n-Y', $timestamp).' '.$end.' America/New_York')) {
				return true;
			}
		}
	}
	return false;
}

//-----
// Calculate time past since timestamp
function get_day_name($timestamp) {
    $date = date('d/m/Y', $timestamp);
    if($date == date('d/m/Y')) {
      $day_name = '<strong>Today</strong>,';
    }else{
		$day_name = '';
	}
    return $day_name;
}

function time_past($timestamp, $type){
	$seconds	= time() - $timestamp;
	$minutes	= $seconds / 60;
	$hours		= $minutes / 60;
	$days		= $hours / 24;

	if($seconds <= 60) {
		$timePast = "".round($seconds, 0)." seconds ago";
	}elseif($minutes <= 60 ){
		$timePast = "".round($minutes, 0)." minutes ago";
	}elseif($hours <= 24 ){
		$timePast = "".round($hours, 0)." hours ago";
	}/*elseif($days < 2 ){
		$timePast = "Yesterday at ".date('g:ia', $timestamp)."";
	}*/elseif($days >= 2 && date('Y', $timestamp) == date('Y')){
		$timePast = "".date('F j', $timestamp)." at ".date('g:ia', $timestamp)."";
	}else{
		$timePast = "".date('n/j/y', $timestamp)." at ".date('g:ia', $timestamp)."";
	}

	if($type == "time"){
		$timePast = date('g:ia');
		$output = $timePast;
	}else{
		$output = $timePast;
	}

	if($type == "day"){
		$output = ''.get_day_name($timestamp).' '.$timePast.'';

	}

	return $output;
}

//+----------------------------------------------------------------------------------------+
//|						Marketocracy Legacy API Transaction Log Function
//+----------------------------------------------------------------------------------------+

// Example
/*
$aMethodVars[] = array(
	'method'		=> 'livePrice',
	'source'		=> 'Login Process | process-auth.php.php | case: login',
	'api'			=> '1',
	'username'		=> $_SESSION['username'],
	'fund_id'		=> $fund_id,
	'fund_symbol'	=> $fund_symbol,
	'group'			=> $_SESSION['member_id'].' - Login Process - '.date('Y-m-d h:i')
);
$mlaResults = legacy_api($mLink, $aMethodVars, true);
*/


function legacy_api($mLink, $aMethodVars, $exec=true){

	$queryLegacyPath 	= 'data-query-legacy.php';
	$pauseCnt 			= 0;
	$methodCnt			= 0;
	$aResults			= array();

	#loop through each posible query
	foreach($aMethodVars as $queryCnt=>$aVars){

		$pauseCnt++;
		$methodCnt++;

		if($pauseCnt >= 50){
			sleep(1);
			$pauseCnt = 0;
		}

		$method 		= $aVars['method'];

		//Get query strings from Db
		$query = "
			SELECT *
			FROM ".$_SESSION['api_methods_table']."
			WHERE method=:method
		";
		try{
			$rsMethods = $mLink->prepare($query);
			$aValues = array(
				':method'	=> $method
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsMethods->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$getMethod = $rsMethods->fetch(PDO::FETCH_ASSOC);

		$aResults['errors']['get_method_queries'][] = $error;

		//Set query vars
		$oldQuery 		= $getMethod['query_old'];
		$newQuery		= $getMethod['query_new'];
		$querySwitch	= $getMethod['query_switch'];

		//Set common vars for use in transaction table
		$api			= $aVars['api'];
		if($api 		== ''){$api = '2';}
		$port			= $aVars['port'];
		$source			= $aVars['source'];
		$group			= $aVars['group'];
		if($group		== ''){ $group = NULL;}
		$fundID			= $aVars['fund_id'];
		if($fundID 		== ''){ $fundID = NULL;}

		//assign port value
		if($port == ''){

			if(!isset($startPort)){

				switch($api){
					case '1':
						$startPort 	= rand(52000, 52099);
						$endPort	= 52099;
					break;

					case '2':
						$startPort 	= rand(52100, 52499);
						$endPort	= 52499;
					break;

					default:
						$startPort 	= rand(52000, 52099);
						$endPort	= 52099;
					break;
				}

				$nextPort = $startPort;
			}else{
				if($nextPort < $endPort){
					$nextPort = $nextPort++;
				}else{
					sleep(1);
					$nextPort = $startPort;
				}
			}

			$port = $nextPort;
		}

		//Deterine which process string to use
		if($querySwitch == 'new'){
			$processing = '1';
			$queryStr	= $newQuery;
		}elseif($querySwitch == 'old'){
			$processing	= '0';
			$queryStr	= $oldQuery;
		}

		foreach($aVars as $var=>$value){
			$queryStr	= str_replace($var, $value, $queryStr);
		}

		$apiQuery = $queryStr;

		//Insert transaction into transaction table
		$query = "
			INSERT INTO ".$_SESSION['legacy_api_trans_table']." (
				api,
				port,
				groups,
				timestamp,
				fund_id,
				method,
				query,
				submission_timestamp,
				processing,
				source
			)VALUES(
				:api,
				:port,
				:groups,
				UNIX_TIMESTAMP(),
				:fund_id,
				:method,
				:query,
				UNIX_TIMESTAMP(),
				:processing,
				:source
			)
		";
		try{
			$rsInsertTrans = $mLink->prepare($query);
			$aValues = array(
				//':trans_id'		=> $transID,
				':api'			=> $api,
				':port'			=> $port,
				':groups'		=> $group,
				':fund_id'		=> $fundID,
				':method'		=> $method,
				':query'		=> $apiQuery,
				':source'		=> $source,
				':processing'	=> $processing
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsInsertTrans->execute($aValues);
		}

		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$aResults['errors']['insert_trans_errors'][] = $error;

		$transID = $mLink->lastInsertId();

		//Execute query to daemon
		$apiQuery 	= str_replace('trans-id', $transID, $apiQuery);
		$query 		= $apiQuery;

		if($exec == true){
			include('data-query-legacy.php');

			$event = 'MLA : '.$method;
			$detail = $source.' : '.$query;
			addEventRecord($mLink, $_SESSION['member_id'], $event, $detail);

			$aResults['submissions'][$methodCnt]['query'] = $query;
			$aResults['submissions'][$methodCnt]['port'] = $port;
			$aResults['submissions'][$methodCnt]['api'] = $api;
		}

	}//end foreach query

	return $aResults;

}
?>
