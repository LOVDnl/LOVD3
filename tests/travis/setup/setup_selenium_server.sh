#!/bin/bash

## This file is used in Travis CI.
## In this file composer is used to install the dependencies defined in composer.json
## Then the selenium server is downloaded and started.
## When the selenium server is not started this script exits 1. And in Travis the tests will fail.
serverUrl='http://127.0.0.1:4444'
seleniumDownloadURL="http://selenium-release.storage.googleapis.com/3.9/selenium-server-standalone-3.9.1.jar"
# Currently using a fixed version of the chrome driver.
# chromeDriverVersion=$(curl http://chromedriver.storage.googleapis.com/LATEST_RELEASE)
chromeDriverVersion="80.0.3987.16"
chromeDriverURL="http://chromedriver.storage.googleapis.com/${chromeDriverVersion}/chromedriver_linux64.zip"
geckoDriverURL="https://github.com/mozilla/geckodriver/releases/download/v0.26.0/geckodriver-v0.26.0-linux64.tar.gz"

echo "Download Selenium"
if [ ! -f ${seleniumDownloadURL} ]; then
    curl -L -O ${seleniumDownloadURL}
fi
serverFile=${seleniumDownloadURL##*/}
if [ ! -e ${serverFile} ]; then
    echo "Cannot find Selenium Server!"
    exit 1
fi

echo "Download chromedriver from ${chromeDriverURL}";
chromeDriverArchive=${chromeDriverURL##*/}
curl -L -O ${chromeDriverURL}
if [ ! -f ${chromeDriverArchive} ]; then
    echo "Download of $chromeDriverURL failed. Aborting."
    exit 1
fi
unzip ${chromeDriverArchive}
if [ ! -f "chromedriver" ]; then
    echo "Failed installing chromedriver. Aborting."
    exit 1
fi

echo "Download geckodriver from ${geckoDriverURL}";
geckoDriverArchive=${geckoDriverURL##*/}
curl -L -O ${geckoDriverURL}
if [ ! -f ${geckoDriverArchive} ]; then
    echo "Download of $geckoDriverURL failed. Aborting."
    exit 1
fi
tar -xzf ${geckoDriverArchive}
if [ ! -f "geckodriver" ]; then
    echo "Failed installing geckodriver. Aborting."
    exit 1
fi

echo "Starting Selenium"
sudo java -Djava.net.preferIPv4Stack=true \
    -Dwebdriver.chrome.driver=chromedriver \
    -Dwebdriver.gecko.driver=geckodriver \
    -jar ${serverFile} -port 4444 \
    > /tmp/selenium.log 2> /tmp/selenium_error.log &
sleep 3
cat /tmp/selenium.log

wget --retry-connrefused --tries=10 --waitretry=3 --output-file=/dev/null ${serverUrl}/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!"
    echo "Selenium STDERR:"
    cat /tmp/selenium_error.log
    exit 1
else
    echo "Finished setup and selenium is started"
fi
