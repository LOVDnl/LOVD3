#!/bin/bash

## This file is used in Travis CI.
## In this file composer is used to install the dependencies defined in composer.json
## Then the selenium server is downloaded and started. 
## When the selenium server is not started this script exits 1. And in Travis the tests will fail.
serverUrl='http://127.0.0.1:4444'
serverFile=selenium-server-standalone-2.53.1.jar
chromeDriverSrc=http://chromedriver.storage.googleapis.com/2.23/chromedriver_linux64.zip

phpVersion=`php -v`

echo "Installing dependencies"
composer install

#echo "check firefox version"
#firefox --version

echo "Download Selenium"
if [ ! -f $serverFile ]; then
    wget http://selenium-release.storage.googleapis.com/2.53/$serverFile
fi
if [ ! -e ${serverFile} ]; then
    echo "Cannot find Selenium Server!"
    echo "Test is aborted"
    exit
fi

echo "Download chromedriver"
driverArchive=${chromeDriverSrc##*/}
curl $chromeDriverSrc > $driverArchive
if [ ! -f $driverArchive ]; then
    echo "Download of $chromeDriverSrc failed. Aborting."
    exit
fi
unzip $driverArchive
if [ ! -f "chromedriver" ]; then
    echo "Failed installing chromedriver. Aborting."
    exit
fi

echo "Starting xvfb and Selenium"
export DISPLAY=:99.0

#sh -e /etc/init.d/xvfb start
#sleep 3
sudo java -jar $serverFile -port 4444 -Djava.net.preferIPv4Stack=true -Dwebdriver.chrome.driver=chromedriver > /tmp/selenium.log 2> /tmp/selenium_error.log &

sleep 3

wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null $serverUrl/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!"
    exit 1
else
    echo "Finished setup and selenium is started"
fi
