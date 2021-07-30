#!/bin/bash

# The purpose of this script is to perform garbage collection on API call input and output files, deleting all those older than one day

# Declare the directories containing the files
directories=("/api/fundprice_processing/*" "/api/stockprice_processing/*" "/api/ecn_processing/*" "/api/manageradmin_processing/*" "/api/trade_processing/*" "/api/ca_processing/*" "/api2/fundprice_processing/*" "/api2/stockprice_processing/*" "/api2/ecn_processing/*" "/api2/manageradmin_processing/*" "/api2/trade_processing/*" "/api2/ca_processing/*" "/api3/fundprice_processing/*" "/api3/stockprice_processing/*" "/api3/ecn_processing/*" "/api3/manageradmin_processing/*" "/api3/trade_processing/*" "/api3/ca_processing/*")

# Loop through them
for dir in "${directories[@]}"
do
	# Find all the files that contain the words "input", "output", "processing" and "tmp" that are more than 1 day old, excluding any subdirectories, and delete them
	find $dir -type d -prune -o -name "*input*" -mtime +1 -exec rm {} \;
	find $dir -type d -prune -o -name "*output*" -mtime +1 -exec rm {} \;
        find $dir -type d -prune -o -name "*processing*" -mtime +1 -exec rm {} \;
	find $dir -type d -prune -o -name "*tmp*" -mtime +1 -exec rm {} \;
done
