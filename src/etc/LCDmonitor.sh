#!/usr/bin/env sh
# KONTROL UTM - LCDd Daemon monitoring script

# Cheking if LCDd daemon service is installed.
if [ ! -d /usr/local/share/Kontrol-pkg-LCDproc ]; then {
echo "Service not installed";
exit;
}
fi


#Checking if LCDd Daemon service is Running
if [ ! `/bin/pgrep -x "LCDd"` ]; then echo "LCD Daemon is not running - Start it from GUI"; exit
else
echo "The LCDd Daemon is running"
fi

# Checking if we have duplicated client processes
NOP=`/bin/ps -wwuxa | /usr/bin/grep -I lcdproc_client.php | /usr/bin/grep -v "grep" | /usr/bin/wc -l`
# If there are more than 1 process, then print message
if [ $NOP -gt 1 ] ; then echo "More than 1 process is running - Killing Processes and restarting the service";
/bin/ps -wwxa | /usr/bin/grep lcdproc_client.php | /usr/bin/grep -v "grep" | /usr/bin/awk '{print $1}' | /usr/bin/xargs kill -9 $1
/bin/sleep 2
#Restating the LCDd client
/usr/local/bin/php -f /usr/local/pkg/lcdproc_client.php &
fi

# If there is no client started, We start a single one.
if [ $NOP -eq 0 ] ; then echo "No client process is running - Starting one";
#Starting the LCDd client
/usr/local/bin/php -f /usr/local/pkg/lcdproc_client.php &
echo "A single LCDd client was started"
fi

# If there is a single client process then print message
if [ $NOP -eq 1 ] ; then echo "A single client process is running";
fi
