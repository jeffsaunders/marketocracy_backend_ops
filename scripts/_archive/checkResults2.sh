#!/bin/bash

while true ; do

	# Look for files with "output" in their names
#	ls /api/fundprice_processing/*output* > /dev/null 2>&1
#	found=$?
#        ls /api/stockprice_processing/*output* > /dev/null 2>&1
#        ((found+=$?))
#        ls /api/ecn_processing/*output* > /dev/null 2>&1
#        ((found+=$?))
#        ls /api/manageradmin_processing/*output* > /dev/null 2>&1
#        ((found+=$?))

	check=`find /api2/fundprice_processing/ -print -name output | grep -c ""`
	((found=$check))
	((found-=1))
        check=`find /api2/stockprice_processing/ -print -name output | grep -c ""`
        ((found+=$check))
        ((found-=1))
        check=`find /api2/ecn_processing/ -maxdepth 1 -print -name output | grep -c ""`
        ((found+=$check))
        ((found-=2))
        check=`find /api2/manageradmin_processing/ -print -name output | grep -c ""`
        ((found+=$check))
        ((found-=1))
        check=`find /api2/trade_processing/ -print -name output | grep -c ""`
        ((found+=$check))

#echo $found
#exit 0
	# If you found any...
#	if [ "$?" = "0" ]; then
#        if (($found < 4)) ; then
        if (($found > 0)) ; then
#echo "found file(s)"

		# See if results daemon is running already
		processCheck=`/bin/ps -ef | grep r[e]sultsDaemon2.php`

		# If it's not already running...
		if test "$processCheck" == "" ; then

			# Start the results daemon
			/usr/bin/php /var/www/html/daemons/resultsDaemon2.php
#echo
#echo "Start Daemon"

		fi

	# No matching files
#	else
	fi
        if (($found == 0)) ; then

#echo "no file(s)"

	        # See if results daemon is running already
		processCheck=`/bin/ps -ef | grep r[e]sultsDaemon2.php`
#echo $processCheck

	        # If it's running...
		if test "$processCheck" != "" ; then

			# Kill the results daemon
			killall php
#echo "Stop Daemon"

		fi
	fi
done
