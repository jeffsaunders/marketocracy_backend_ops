<?php
// This version runs through a web browser (http://192.168.111.215/batch/utility/printLastDaysHTML.php)

date_default_timezone_set('America/New_York');

$aYears = range(2000, 2017);
//print_r($aYears);

$aMonths = range(1, 12);
//print_r($aMonths);

echo "<table width='800'>";
foreach ($aYears as $year){
	if (($year - 2000) % 6 == 0){
		echo "</tr><tr><td><br></td></tr><tr>";
	}
	echo "<td>";
	foreach ($aMonths as $month){
		$date = $month."/01/".$year;
		$lastDay = date('Ymt', strtotime($date));
		echo $lastDay."<br>";
	}
	echo "</td>";
}
echo "</tr></table>";

?>
