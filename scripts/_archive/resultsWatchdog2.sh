#!/bin/bash
##
#   Watchdog script to make sure the results processing daemon is running
##

# Check to see if the daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckResults2.sh`

echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
	# Start it
	/var/www/html/scripts/checkResults2.sh
fi
