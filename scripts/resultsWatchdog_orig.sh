#!/bin/bash
##
#   Watchdog script to make sure the results processing daemons are running
##

# API1

#--- Check to see if the ecn results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckECNResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkECNResults.sh
fi

#--- Check to see if the manageradmin results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckManageradminResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkManageradminResults.sh
fi

#--- Check to see if the fundprice results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckFundpriceResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkFundpriceResults.sh
fi

#--- Check to see if the stockprice results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckStockpriceResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkStockpriceResults.sh
fi

#--- Check to see if the trade results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckTradeResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkTradeResults.sh
fi

#--- Check to see if the ca results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckCAResults.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkCAResults.sh
fi


# API2

#--- Check to see if the ecn results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckECNResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkECNResults2.sh
fi

#--- Check to see if the manageradmin results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckManageradminResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkManageradminResults2.sh
fi

#--- Check to see if the fundprice results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckFundpriceResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkFundpriceResults2.sh
fi

#--- Check to see if the stockprice results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckStockpriceResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkStockpriceResults2.sh
fi

#--- Check to see if the trade results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckTradeResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkTradeResults2.sh
fi

#--- Check to see if the ca results daemon is running
processCheck=`/bin/ps -ef | grep c[h]eckCAResults2.sh`
echo $processCheck
# If we have a blank response then it's not running
if test "$processCheck" == "" ; then
        # Start it
        /var/www/html/scripts/checkCAResults2.sh
fi

