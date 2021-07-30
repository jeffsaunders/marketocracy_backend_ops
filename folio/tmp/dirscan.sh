#!/bin/bash

#while true; do
##	ls -d | entr -d echo "New!\r"
#        find /var/www/html/folio/tmp/ | entr -d echo "New!"
#done


inotifywait -m -r -q --format '%f' /var/www/html/folio/tmp/*.csv | while read FILE
do
  echo "something happened on $FILE"
done
