serverUrl='http://127.0.0.1:4444'
serverFile=selenium-server-standalone-2.44.0.jar
firefoxUrl=http://ftp.mozilla.org/pub/mozilla.org/firefox/releases/37.0.2/linux-x86_64/en-US/firefox-37.0.2.tar.bz2
firefoxFile=firefox.tar.bz2
phpVersion=`php -v`

sudo apt-get -qq update

##echo "Updating Composer"
##sudo /home/travis/.phpenv/versions/5.3/bin/composer self-update

echo "Installing dependencies"
composer install

echo "Download Firefox"
wget $firefoxUrl -O $firefoxFile
tar xvjf $firefoxFile

echo "Download Selenium"
if [ ! -f $serverFile ]; then
    wget http://selenium-release.storage.googleapis.com/2.44/$serverFile
fi
if [ ! -e ${serverFile} ]; then
    echo "Cannot find Selenium Server!"
    echo "Test is aborted"
    exit
fi

echo "Starting xvfb and Selenium"
sudo xvfb-run java -jar $serverFile > /tmp/selenium.log &
wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null $serverUrl/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!"
    exit
else
    echo "Finished setup and selenium is started"
fi
