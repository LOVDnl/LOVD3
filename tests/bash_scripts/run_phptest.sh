#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPTPATH=$(dirname $SCRIPT)
TESTPATH=$(dirname $SCRIPTPATH)
SELENIUMTESTFOLDER=${TESTPATH}/phpunit_selenium

# used for the report name.
DATE=`date +%Y-%m-%d:%H:%M:%S`

# Default location of phpunit folder
PHPUNITFOLDER=/home/$USER/bin/vendor/phpunit/phpunit/

# get the exact location of the selenium server.
SELENIUMSERVER=`locate /bin/selenium-server-standalone-2.44.0.jar`

for i in "$@"
do
    case $i in
        -f=*|--file=*)
            FILE="${i#*=}"
            # Check if folder exists.
            if [ ! -e ${SELENIUMTESTFOLDER}/${FILE} ]; then
                echo "File" $FILE " does not exists"
                echo "Test is aborted"
                exit
            fi
        ;;
        -p=*|--phpunit=*)
            PHPUNITFOLDER="${i#*=}"
            # check if input is correct is done later.
        ;;
        --default)
            DEFAULT=YES
        ;;
        *)
            echo Unknown input
            echo Usage:
            column -t -s "/" <<<'    -f=<file> /|/ --file=<file> / To test one specific file which is loceted in the phpunit_selenium folder.
        -p=<folder> /|/ --phpunit=<folder> / Define the phpunit folder when you can not call phpunit directly.'
            echo "Specify no file when you want te test all testfiles in the phpunit_selenium folder."
            exit
        ;;
    esac
done

# Check if phpunit folder exists.
if [ ! -e ${PHPUNITFOLDER}phpunit ]; then
    echo "phpunit is not found in folder:" $PHPUNITFOLDER
    echo "Test is aborted"
    exit
fi

# If the selenium server is already running, then the selenium server is not started again.
javaruns=`ps -ef | grep selenium-server | grep -v grep | wc -l`
if [ $javaruns = 0 ]; then
    if [ ! -e ${SELENIUMSERVER} ]; then
        echo "Cannot find Selenium Server!"
        echo "Test is aborted"
        exit
    fi
    echo "Start Selenium Server"
    gnome-terminal -e "java -jar ${SELENIUMSERVER} -trustAllSSLCertificates" & sleep 2s
    PID=`ps -ef |grep selenium-server | grep -v grep | awk '{print $2}'`
    # check if selenium server is started. PID (processID) is later used to kill the selenium-server
    if [ -z "$PID" ]; then
        echo "Selenium Server is not started!"
        echo "Test is aborted"
        exit
    fi
fi

echo "Start PHPUnit test"
${PHPUNITFOLDER}phpunit --log-junit ${TESTPATH}/test_results/reports/testlog_${DATE}.xml ${SELENIUMTESTFOLDER}/${FILE}

if [ -n "$PID" ]; then
    echo "Close Selenium Server"
    kill $PID
fi
