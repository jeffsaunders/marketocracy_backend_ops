#!/usr/bin/expect -f

# Script using the EXPECT shell to call on the legacyDataDaemon on the Process server.

set arg1 [lindex $argv 0]
set arg2 [lindex $argv 1]
#send_user $arg1\n
#send_user $arg2\n
set timeout 5
spawn telnet 192.168.111.215 "$arg1"
expect "Escape character is '^]'."
send "$arg2\n"
expect "Success"
