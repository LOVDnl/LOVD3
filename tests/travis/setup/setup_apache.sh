#!/bin/bash

# This file configures the apache server.
# The githubaccount parameter is required. This will be the root folder for localhost.
for i in "$@"
do
    case $i in
        -ga=*|--githubaccount=*)
            GITHUBACCOUNT="${i#*=}"
			#The input is refering to the github account.
			#For local development: /home/travis/build/dasscheman
			#For LUMC development : /home/travis/build/LUMC
        ;;
        *)
            echo Unknown input
            echo Usage:
            column -t -s "/" <<<'    -l=<folder> /|/ --localhost=<folder> / Give the first localhost folder when it is not "svn". This is used in the Travis CI test.
        -c /|/ --continueall / If set, it will not ask for actions during convert, but always continues with convert. This might create corrupt phpunit test files.'
            echo "Specify no file when you want te test all testfiles in the phpunit_selenium folder."
            exit
        ;;
    esac
done

echo "Install and setup apache"

sudo a2enmod rewrite

## Setting the home directory for localhost.
# setup_apache.sh assumes that the files are in a folder with this pattern: /githubaccount/projectname/
echo 'Set localhost directory.'
sudo sed -i -e "s,/var/www/html,/home/travis/build/${GITHUBACCOUNT},g" /etc/apache2/sites-available/000-default.conf
sudo sed -i -e "s,/var/www,/home/travis/build/${GITHUBACCOUNT},g" /etc/apache2/apache2.conf
sudo sed -i -e "s,AllowOverride[ ]None,AllowOverride All,g" /etc/apache2/apache2.conf

# Set server administrator directive (used for installation signature that is
# sent to lovd.nl for identification).
sudo sed -i -e "s,ServerAdmin webmaster@localhost,ServerAdmin travis-ci@localhost,g" /etc/apache2/sites-available/000-default.conf

# Make all source files readable and all source dirs executable.
# (requirement of Apache)
sudo chmod -R +r /home/travis
sudo find /home/travis -type d -exec chmod +x {} \;

echo 'Restart apache2'
sudo /etc/init.d/apache2 restart

# Make sure the error logs are readable for us.
sudo chmod +rx /var/log/apache2
sudo chmod +r /var/log/apache2/error.log
