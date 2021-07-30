#!/bin/bash

#--- Check to see if apiQueue.php is running already
processCheck=`/bin/ps -ef | grep apiQueue.php | grep -v grep` # Ignore grep itself
#echo $processCheck
# If we have a blank response then it's not running
if [ -z "$processCheck" ]; then
	# Start it
	/usr/bin/php /var/www/html/batch/apiQueue.php
fi


