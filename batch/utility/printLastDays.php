<?php

date_default_timezone_set('America/New_York');

$aYears = range(2016, 2000);  // Reverse order
//print_r($aYears);

$aMonths = range(12, 1);  // Reverse order
//print_r($aMonths);

foreach ($aYears as $year){
	foreach ($aMonths as $month){
		$date = $month."/01/".$year;
		$lastDay = date('Ymt', strtotime($date));
		echo $lastDay."\n";
	}
	echo "\n";
}

?>
