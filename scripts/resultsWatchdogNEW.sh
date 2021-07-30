#!/bin/bash
##
#   Watchdog script to make sure the results processing daemons are running
##

# Declare the scripts to check for and run if not running (no spaces allowed around equal sign)
scripts=("c[h]eckECNResults.sh"
	 "c[h]eckManageradminResults.sh"
	 "c[h]eckFundpriceResults.sh"
	 "c[h]eckStockpriceResults.sh"
	 "c[h]eckTradeResults.sh"
	 "c[h]eckCAResults.sh"
	 "c[h]eckECNResults2.sh"
         "c[h]eckManageradminResults2.sh"
         "c[h]eckFundpriceResults2.sh"
         "c[h]eckStockpriceResults2.sh"
         "c[h]eckTradeResults2.sh"
         "c[h]eckCAResults2.sh"
)
#scripts=("c[h]eckECNResults.sh"
#         "c[h]eckManageradminResults.sh"
#         "c[h]eckFundpriceResults.sh"
#         "c[h]eckStockpriceResults.sh"
#         "c[h]eckTradeResults.sh"
#         "c[h]eckCAResults.sh"
#         "c[h]eckECNResults2.sh"
#         "c[h]eckManageradminResults2.sh"
#         "c[h]eckFundpriceResults2.sh"
#         "c[h]eckStockpriceResults2.sh"
#         "c[h]eckTradeResults2.sh"
#         "c[h]eckCAResults2.sh"
#         "c[h]eckECNResults3.sh"
#         "c[h]eckManageradminResults3.sh"
#         "c[h]eckFundpriceResults3.sh"
#         "c[h]eckStockpriceResults3.sh"
#         "c[h]eckTradeResults3.sh"
#         "c[h]eckCAResults3.sh"
#)

for script in "${scripts[@]}" ; do
	
	# Strip out the brackets to get the actual daemon filename
	daemon=${script/[/} # Remove [ from $script
	daemon=${daemon/]/} # Remove ] from $daemon (just assigned)

	#--- Check to see if the ecn results daemon is running
	processCheck=`/bin/ps -ef | grep "$script"` # Quotes force raw value - brackets in place
	echo $processCheck
	# If we have a blank response then it's not running
	if test "$processCheck" == "" ; then
       	 	# Start it
       	 	/var/www/html/scripts/$daemon
	fi

done




