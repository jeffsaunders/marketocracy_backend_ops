<?php



for ($count = 500; $count < 700; $count++){

	$fp = fopen("/etc/xinetd.d/legacyData".$count, "w");

	$string = "# default: on
# description: legacyData service.  Listens then executes XML API call for legacy (old system) data.  Writes results to database.
service legacyData".$count."
{
        disable                 = no
        socket_type             = stream
        protocol                = tcp
        wait                    = no
        user                    = root
        server                  = /usr/bin/php
        server_args             = /var/www/html/daemons/legacyDataDaemon3.php
        only_from               = 192.168.111.0/24
        log_on_success          += DURATION EXIT
        nice                    = 10
        #instances               = 1
}
";
//die($string);
//$string = "Test";
	fwrite($fp, $string, 1024);

	fclose($fp);

	chmod("/etc/xinetd.d/legacyData".$count, 0755);

}
?>