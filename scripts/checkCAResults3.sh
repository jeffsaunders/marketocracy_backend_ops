#!/bin/bash

#set arg1 [lindex $argv 0]
#set arg2 [lindex $argv 1]

while true ; do

	# Look for files with "output" in their names
#        check=`find /api3/ca_processing/output/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        check=`find /api3/ca_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
        check=`ls -al /api3/ca_processing/ca_output_* | wc -l`
        ((found+=$check))
#echo $found
#exit 0

	# If you found any...
        if (($found > 0)) ; then
#echo "found file(s)"

		# See if results daemon is running already
		processCheck=`/bin/ps -ef | grep CAR[e]sultsDaemon3.php`

		# If it's not already running...
		if test "$processCheck" == "" ; then

			# Start the results daemon
			/usr/bin/php /var/www/html/daemons/CAResultsDaemon3.php
#echo
#echo "Start Daemon"

		fi
	fi

	# No matching files
        if (($found == 0)) ; then
#echo "no file(s)"

	        # See if results daemon is running already
		processCheck=`/bin/ps -ef | grep CAR[e]sultsDaemon3.php`
#echo $processCheck

	        # If it's running...
		if test "$processCheck" != "" ; then

			# Kill the results daemon
                        ps -ef | awk '$NF~"CAResultsDaemon3.php" {print $2}'| xargs kill -15 #TERM vs. -9 KILL
#echo "Stop Daemon"

		fi
	fi
done
