#!/usr/bin/expect

# Script using the EXPECT shell to call on the fundPriceDaemon on the Fetch server to determine the most recent date available on the FundPrice server.
# WON'T RUN IN THIS MACHINE, NEEDS EXPECT SHELL

set timeout 20
spawn telnet 192.168.111.215 52066
expect "Escape character is '^]'."
send "maxDate|0\r"
expect "Done"
