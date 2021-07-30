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
		WHERE date = '".date('Y-m-d', $timestamp)."'
	";
//return $query;
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
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:31 PM" : "4:31 PM");
				break;

			case 'both':  // Start 30 minutes early, end 30 minutes late
				$begin = "9:00 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "1:31 PM" : "4:31 PM");
				break;

			case 'indices':  // Start on time, end 2 hours late (after final closing prices are set)
				$begin = "9:30 AM";
				$end = (isMarketHoliday($timestamp, $linkID) == "E" ? "3:01 PM" : "6:01 PM");
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
// Calculate time passed since timestamp
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

?>
