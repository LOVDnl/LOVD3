#!/bin/bash

# This file is used in Travis CI.
# It downloads the selenium server, the chrome driver and the gecko driver.
# Selenium is then started and quickly tested.
# When the selenium server is not started, this script returns a status of 1.

# Before changing any of these versions, ensure they are compatible with each other, and with your browser versions.
seleniumDownloadURL="http://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar"
# Make sure our Chrome Driver matched our Chrome version.
# We're using the latest stable Chrome, but this doesn't always mean the latest stable Chrome driver.
chromeMajorVersion=$(google-chrome --version | cut -d " " -f 3 | cut -d . -f 1);
chromeDriverVersion=$(curl -s "http://chromedriver.storage.googleapis.com/LATEST_RELEASE_${chromeMajorVersion}")
chromeDriverURL="http://chromedriver.storage.googleapis.com/${chromeDriverVersion}/chromedriver_linux64.zip"
# https://firefox-source-docs.mozilla.org/testing/geckodriver/Support.html
geckoDriverURL="https://github.com/mozilla/geckodriver/releases/download/v0.26.0/geckodriver-v0.26.0-linux64.tar.gz"

echo "Downloading Selenium from ${seleniumDownloadURL}";
if [ ! -f ${seleniumDownloadURL} ]; then
    curl -sLO ${seleniumDownloadURL}
fi
serverFile=${seleniumDownloadURL##*/}
if [ ! -e ${serverFile} ]; then
    echo "Cannot find Selenium Server!"
    exit 1
fi

echo "Downloading chromedriver from ${chromeDriverURL}";
chromeDriverArchive=${chromeDriverURL##*/}
curl -sLO ${chromeDriverURL}
if [ ! -f ${chromeDriverArchive} ]; then
    echo "Download of $chromeDriverURL failed. Aborting."
    exit 1
fi
unzip ${chromeDriverArchive}
if [ ! -f "chromedriver" ]; then
    echo "Failed installing chromedriver. Aborting."
    exit 1
fi

echo "Downloading geckodriver from ${geckoDriverURL}";
geckoDriverArchive=${geckoDriverURL##*/}
curl -sLO ${geckoDriverURL}
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
java -Djava.net.preferIPv4Stack=true \
    -Dwebdriver.chrome.driver=chromedriver \
    -Dwebdriver.gecko.driver=geckodriver \
    -jar ${serverFile} -port 4444 \
    > /tmp/selenium.log 2> /tmp/selenium_error.log &
sleep 3
cat /tmp/selenium.log

wget --retry-connrefused --tries=10 --waitretry=3 --output-file=/dev/null http://127.0.0.1:4444/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!"
    echo "Selenium STDERR:"
    cat /tmp/selenium_error.log
    exit 1
else
    echo "Finished setup and selenium is started"
fi
