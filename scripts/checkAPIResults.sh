#!/bin/bash

###
# checkAPIResults.sh <API#> <method> <daemon check filename>
# /var/www/html/scripts/checkAPIResults.sh 3 ca_processing CAR[e]sultsDaemon3.php
###

api=$1 #API number (numeral)
method=$2 #API method name (e.g. ca_processing)
checkfile=$3 #Daemon name string to test for (e.g. CAR[e]sultsDaemon3.php)

# Now strip out the [] to yield the daemon name
daemon=${checkfile/[/} # Remove [
daemon=${daemon/]/} # Remove ]

#echo $checkfile
#echo $daemon

#exit 0


while true ; do

	# Look for files with "output" in their names
        check=`find /api$api/$method/output/* -type d -prune -o -name "*output*" -print | grep -c ""`
        ((found+=$check))
#echo /api$api/$method/output/
#echo $found
#echo '/bin/ps -ef | grep '"$checkfile"
#echo /usr/bin/php /var/www/html/daemons/$daemon
#exit 0

	# If you found any...
        if (($found > 0)) ; then
#echo "found file(s)"

		# See if results daemon is running already
		processCheck=`/bin/ps -ef | grep $checkfile`
#echo $processCheck
#exit 0
		# If it's not already running...
		if test "$processCheck" == "" ; then

			# Start the results daemon
			/usr/bin/php /var/www/html/daemons/$daemon
#echo
#echo "Start Daemon"

		fi
	fi

	# No matching files
        if (($found == 0)) ; then
#echo "no file(s)"

	        # See if results daemon is running already
		processCheck=`/bin/ps -ef | grep $checkfile`
#echo $processCheck

	        # If it's running...
		if test "$processCheck" != "" ; then

			# Kill the results daemon
                        ps -ef | awk '$NF~"$daemon" {print $2}'| xargs kill -15 #TERM vs. -9 KILL
#echo "Stop Daemon"

		fi
	fi
done
