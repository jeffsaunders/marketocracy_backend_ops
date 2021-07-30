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

#	check=`find /api/fundprice_processing/ -print -name output | grep -c ""`
#	((found=$check))
#	((found-=1))
#       check=`find /api/stockprice_processing/ -print -name output | grep -c ""`
#        ((found+=$check))
#        ((found-=1))
#        check=`find /api/ecn_processing/ -maxdepth 1 -print -name output | grep -c ""`
#        ((found+=$check))
#        ((found-=2))
#        check=`find /api/manageradmin_processing/ -print -name output | grep -c ""`
#        ((found+=$check))
#        ((found-=1))

#        check=`find /api/fundprice_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        ((found=$check))
#        ((found-=1))
####        check=`find /api2/stockprice_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
        check=`ls -al /api2/stockprice_processing/stockprice_output_* | wc -l`
        ((found+=$check))
#        ((found-=1))
#        check=`find /api/ecn_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        ((found+=$check))
#        ((found-=2))
#        check=`find /api/manageradmin_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        ((found+=$check))
#        ((found-=1))
#        check=`find /api/ranking_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        ((found+=$check))
#        ((found-=1))
#        check=`find /api/trade_processing/* -type d -prune -o -name "*output*" -print | grep -c ""`
#        ((found+=$check))
#        ((found-=1))

#echo $found
#exit 0
	# If you found any...
#	if [ "$?" = "0" ]; then
#        if (($found < 4)) ; then
        if (($found > 0)) ; then
#echo "found file(s)"

		# See if results daemon is running already
		processCheck=`/bin/ps -ef | grep stockpriceR[e]sultsDaemon2.php`

		# If it's not already running...
		if test "$processCheck" == "" ; then

			# Start the results daemon
			/usr/bin/php /var/www/html/daemons/stockpriceResultsDaemon2.php
#echo
#echo "Start Daemon"

		fi

	# No matching files
#	else
	fi
        if (($found == 0)) ; then

#echo "no file(s)"

	        # See if results daemon is running already
		processCheck=`/bin/ps -ef | grep stockpriceR[e]sultsDaemon2.php`
#echo $processCheck

	        # If it's running...
		if test "$processCheck" != "" ; then

			# Kill the results daemon
			#killall php
                        ps -ef | awk '$NF~"stockpriceResultsDaemon2.php" {print $2}'| xargs kill -15 #TERM vs. -9 KILL
#echo "Stop Daemon"

		fi
	fi
done
