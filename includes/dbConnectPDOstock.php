<?php
//Set Database Variables

$dbHost = "192.168.111.211";
$dbName = "stock";
$dbUser = "marketocracy";
$dbPass = "KfabyZcbE3";

//Connect to portfolio DB / MySQL with PDO_MYSQL
try{
	$stLink = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
	$stLink->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
}
catch(PDOException $error){
// Log any error to /var/log/httpd/redshift-pdo_log
#            file_put_contents("/var/log/httpd/redshift-pdo_log", "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
            die($error->getMessage());
}

?>


