#!/bin/bash

#
# watchdog script to make sure getIndicesLive php script is running
#

# Check to see if it's running
processCheck=`/bin/ps -ef | grep -v $$ | grep getIndicesLive.php`

# If we have a blank response then it's not running
if test "$processCheck"="" ; then
	# Start it
	/usr/bin/php /var/www/html/batch/getIndicesLive.php &
fi

