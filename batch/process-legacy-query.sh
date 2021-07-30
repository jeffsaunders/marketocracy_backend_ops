#!/usr/bin/expect

# Script using the EXPECT shell to call on the legacyDataDaemon on this server.

set arg1 [lindex $argv 0]
set arg2 [lindex $argv 1]
set timeout 20
spawn telnet 192.168.111.215 "$arg1"
expect "Escape character is '^]'."
send "$arg2\n"
expect "Success"
