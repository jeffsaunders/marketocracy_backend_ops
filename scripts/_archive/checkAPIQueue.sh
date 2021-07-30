#!/bin/bash

#while true ; do
#do

	# See if apiQueue.php is running already
	processCheck=`/bin/ps -ef | grep apiQ[u]eue.php`

	# If it's not already running...
	if test "$processCheck" == "" ; then

		# Start apiQueue.php
		/usr/bin/php /var/www/html/batch/apiQueue.php

	fi

#                        ps -ef | awk '$NF~"ecnResultsDaemon.php" {print $2}'| xargs kill -15 #TERM vs. -9 KILL
#done
