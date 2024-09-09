#!/bin/bash

# This file is used in Travis CI.
# It downloads the selenium server, the chrome driver and the gecko driver.
# Selenium is then started and quickly tested.
# When the selenium server is not started, this script returns a status of 1.

# To fix issues with running tests locally on our Firefox within a snap container,
#  we have to set a temporary directory that's accessible to snap.
if [[ "$(hostname)" == "elim-2020" ]];
then
    export TMPDIR=/home/ifokkema/tmp/firefox_dont_remove/;
else
    export TMPDIR=/tmp/;
fi

# When running locally, we usually miss this variable.
if [ ! "${LOVD_SELENIUM_DRIVER}" ];
then
  export LOVD_SELENIUM_DRIVER=chrome;
fi;

# Before changing any of these versions, ensure they are compatible with each other, and with your browser versions.
seleniumDownloadURL="http://selenium-release.storage.googleapis.com/3.141/selenium-server-standalone-3.141.59.jar";
seleniumJAR="${seleniumDownloadURL##*/}";

# Download Selenium, but only when we don't have it already, to save time.
if [ ! -f "${seleniumJAR}" ];
then
  echo "Downloading Selenium from ${seleniumDownloadURL}...";
  curl -sLO "${seleniumDownloadURL}";
  if [ ! -f "${seleniumJAR}" ];
  then
    echo "Download of ${seleniumDownloadURL} failed. Aborting.";
    exit 1;
  fi;
fi;



# Install chrome driver, but only when using chrome and only when we don't have it already, to save time.
if [ "${LOVD_SELENIUM_DRIVER}" == "chrome" ] && [ ! -f "chromedriver" ];
then
  # Make sure our Chrome Driver matched our Chrome version.
  # We're using the latest stable Chrome, but this doesn't always mean the latest stable Chrome driver.
  chromeMajorVersion=$(google-chrome --version | cut -d " " -f 3 | cut -d . -f 1);
  chromeDriverVersion=$(curl -s "https://googlechromelabs.github.io/chrome-for-testing/LATEST_RELEASE_${chromeMajorVersion}");
  chromeDriverURL="https://storage.googleapis.com/chrome-for-testing-public/${chromeDriverVersion}/linux64/chromedriver-linux64.zip";

  echo "Downloading chromedriver from ${chromeDriverURL}...";
  chromeDriverArchive="${chromeDriverURL##*/}";
  curl -sLO "${chromeDriverURL}";
  if [ ! -f "${chromeDriverArchive}" ];
  then
    echo "Download of ${chromeDriverURL} failed. Aborting.";
    exit 1;
  fi;
  unzip "${chromeDriverArchive}";
  if [ ! -f "chromedriver" ];
  then
    echo "Failed installing chromedriver. Aborting.";
    exit 1;
  fi;
fi;



# Install gecko driver, but only when using firefox and only when we don't have it already, to save time.
if [ "${LOVD_SELENIUM_DRIVER}" == "firefox" ] && [ ! -f "geckodriver" ];
then
  # https://firefox-source-docs.mozilla.org/testing/geckodriver/Support.html
  geckoDriverURL="https://github.com/mozilla/geckodriver/releases/download/v0.34.0/geckodriver-v0.34.0-linux64.tar.gz";

  echo "Downloading geckodriver from ${geckoDriverURL}...";
  geckoDriverArchive="${geckoDriverURL##*/}";
  curl -sLO "${geckoDriverURL}";
  if [ ! -f "${geckoDriverArchive}" ];
  then
    echo "Download of ${geckoDriverURL} failed. Aborting.";
    exit 1;
  fi;
  tar -xzf "${geckoDriverArchive}";
  if [ ! -f "geckodriver" ];
  then
    echo "Failed installing geckodriver. Aborting.";
    exit 1;
  fi;
fi;



echo "Starting Selenium...";
java -Djava.net.preferIPv4Stack=true \
    -Dwebdriver.chrome.driver=chromedriver \
    -Dwebdriver.gecko.driver=geckodriver \
    -Djava.io.tmpdir="$TMPDIR" \
    -jar "${seleniumJAR}" -port 4444 \
    > /tmp/selenium.log 2> /tmp/selenium_error.log &
sleep 3;
cat /tmp/selenium.log;

wget --retry-connrefused --tries=10 --waitretry=3 --output-file=/dev/null http://127.0.0.1:4444/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!";
    echo "Selenium STDERR:";
    cat /tmp/selenium_error.log;
    exit 1;
else
    echo "Finished setup and selenium is started.";
fi
