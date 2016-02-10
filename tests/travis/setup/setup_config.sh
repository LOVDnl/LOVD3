#!/bin/bash

## The config.ini.php file is not included in github. 
## This script creates the config.ini.php file an sets the username and password
## for the Travis CI build.

pwd

exit 1
echo "Copy lovd config file"
cp ./config.ini.php-lovd ./config.ini.php

echo "Set username and password"

config=`grep -A 2000 "<?php" config.ini.php |
    sed "s@username =@username = lovd@" |
    sed "s@password =@password = lovd_pw@" |
    sed 's@database =@database = lovd3_development@' |
    sed "s@table_prefix = lovd@table_prefix = lovd_v3@"`
echo "${config}">config.ini.php
