serverUrl='http://127.0.0.1:4444'
serverFile=selenium-server-standalone-2.50.1.jar

phpVersion=`php -v`

echo "Installing dependencies"
composer install

echo "check firefox version"
firefox --version

echo "Download Selenium"
if [ ! -f $serverFile ]; then
    wget http://selenium-release.storage.googleapis.com/2.50/$serverFile
fi
if [ ! -e ${serverFile} ]; then
    echo "Cannot find Selenium Server!"
    echo "Test is aborted"
    exit
fi

echo "Starting xvfb and Selenium"
export DISPLAY=:99.0

## You can start the selenium in two ways. The second method prints all selenium 
## server logs in travis. This might give long logs errors. Therefore the first 
## method is preferred. The second one might be convenient when debugging.
# 1:
sudo xvfb-run java -jar $serverFile > /tmp/selenium.log &

# 2:
#sh -e /etc/init.d/xvfb start
#sleep 3
#sudo java -jar $serverFile -port 4444 > /tmp/selenium.log &

sleep 3

wget --retry-connrefused --tries=120 --waitretry=3 --output-file=/dev/null $serverUrl/wd/hub/status -O /dev/null
if [ ! $? -eq 0 ]; then
    echo "Selenium Server not started --> EXIT!"
    exit 1
else
    echo "Finished setup and selenium is started"
fi
