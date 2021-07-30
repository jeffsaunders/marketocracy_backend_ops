<?php
/*
The purpose of this page is to convert a passed UNIXTIME value to human readable and display time across all North America zime zones.
*Note - this must be run in a web browser.
*/

// Define some system settings
date_default_timezone_set('America/New_York');

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

echo '
<!DOCTYPE HTML>

<html>
<head>
	<title>UNIXTIME Tool</title>
</head>

<body>

<form action="" name="unixtime" id="unixtime">
	Enter UnixTime <span style="font-size:9pt">(leave blank for "NOW")</span>:
	<input type="text" name="unixtime" size="15" autofocus="autofocus" value="">
	<input type="submit">
</form>

<br>
';

// testing (DST) 1435708800

// Set the time to convert
$unixtime = $_REQUEST['unixtime'];
if ($unixtime == ''){
	$unixtime = time();
}

// Step through the timezones and convert to "local" time
date_default_timezone_set('UTC');
$UTC = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Atlantic
date_default_timezone_set('America/Halifax');
$AT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Puerto Rico
date_default_timezone_set('America/Puerto_Rico');
$PRT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Eastern
date_default_timezone_set('America/New_York');
$ET = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Central
date_default_timezone_set('America/Chicago');
$CT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Mountain
date_default_timezone_set('America/Denver');
$MT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Arizona (No DST)
date_default_timezone_set('America/Phoenix');
$AZT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Pacific
date_default_timezone_set('America/Los_Angeles');
$PT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Alaska
date_default_timezone_set('America/Anchorage');
$AKT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Hawaii
date_default_timezone_set('Pacific/Honolulu');
$HT = date("m/d/Y @ h:i:s a (P)", $unixtime);

// Display it
echo "
<table border = '1' cellpadding = '5' cellspacing = '0'>
<tr style='font-weight:bold'>
	<td>Unixtime:</td>
	<td>".$unixtime."</td>
</tr>
<tr>
	<td>Greenwich Mean Time (UTC):</td>
	<td>".$UTC."</td>
</tr>
<tr>
	<td>Atlantic ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (A".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$AT."</td>
</tr>
<tr>
	<td>Puerto Rico Standard Time (No DST):</td>
	<td>".$PRT."</td>
</tr>
<tr style='font-weight:bold;'>
	<td>Eastern ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (E".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$ET."</td>
</tr>
<tr>
	<td>Central ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (C".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$CT."</td>
</tr>
<tr>
	<td>Mountain ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (M".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$MT."</td>
</tr>
<tr>
	<td>Arizona Standard Time (No DST):</td>
	<td>".$AZT."</td>
</tr>
<tr>
	<td>Pacific ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (P".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$PT."</td>
</tr>
<tr>
	<td>Alaska ".(date('I', $unixtime) == 1 ? "Daylight" : "Standard")." Time (AK".(date('I', $unixtime) == 1 ? "D" : "S")."T):</td>
	<td>".$AKT."</td>
</tr>
<tr>
	<td>Hawaii Standard Time (No DST):</td>
	<td>".$HT."</td>
</tr>
<table>

<br><br>

</body>
</html>
";

?>

