#!/bin/bash

## The config.ini.php file is not included in github. 
## This script creates the config.ini.php file an sets the username and password
## for the Travis CI build.

pwd

echo "Set username and password"
echo "and create lovd config file"

config=`grep -A 2000 "<?php" ./src/config.ini.php-lovd |
    sed "s@username =@username = lovd@" |
    sed "s@password =@password = lovd_pw@" |
    sed 's@database =@database = lovd3_development@' |
    sed "s@table_prefix = lovd@table_prefix = lovd_v3@"`
echo "${config}">./src/config.ini.php
