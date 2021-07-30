#!/bin/bash
##
#   Watchdog script to make sure the results processing daemons are running
##

# Declare the scripts to check for and run if not running (no spaces allowed around equal sign)
scripts=("checkECNResults.sh"
         "checkManageradminResults.sh"
         "checkFundpriceResults.sh"
         "checkStockpriceResults.sh"
         "checkTradeResults.sh"
         "checkCAResults.sh"
         "checkECNResults2.sh"
         "checkManageradminResults2.sh"
         "checkFundpriceResults2.sh"
         "checkStockpriceResults2.sh"
         "checkTradeResults2.sh"
         "checkCAResults2.sh"
         "checkECNResults3.sh"
         "checkManageradminResults3.sh"
         "checkFundpriceResults3.sh"
         "checkStockpriceResults3.sh"
         "checkTradeResults3.sh"
         "checkCAResults3.sh"
)

for script in "${scripts[@]}" ; do
	
	#--- Check to see if each results daemon is running
        processCheck=`/bin/ps -ef | grep "$script" | grep -v grep` # Ignore grep itself
#	echo $processCheck
	# If we have a blank response then it's not running
        if [ -z "$processCheck" ]; then
       	 	# Start it
                /var/www/html/scripts/$script
	fi

done



