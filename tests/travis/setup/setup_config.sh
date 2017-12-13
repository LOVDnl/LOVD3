#!/bin/bash

## The config.ini.php file is not included in github. 
## This script creates the config.ini.php file an sets the username and password
## for the Travis CI build.

pwd

echo "Set username and password"
echo "and create lovd config file"

# Create directories for storing/archiving automatic submissions.
mkdir -m a=rwx /home/travis/build/data_files
mkdir -m a=rwx /home/travis/build/data_files_archive

cat << EOF > ./src/config.ini.php
                                <?php exit(); ?>
#################### DO NOT MODIFY OR REMOVE THE LINE ABOVE ####################
################################################################################
#                              LOVD settings file                              #
#                                    v. 3.0                                    #
################################################################################
#                                                                              #
# Lines starting with # are comments and ignored by LOVD, as are empty lines.  #
#                                                                              #
# Default values of directives are mentioned when applicable. To keep the      #
# default settings, leave the line untouched.                                  #
#                                                                              #
################################################################################



[database]

# Database driver. Defaults to 'mysql'.
driver = mysql

# Database host. Defaults to 'localhost'.
#
hostname = localhost

# Database username and password (required for MySQL).
#
username = lovd
password = lovd_pw

# Database name (required). When using SQLite, specify the filename here.
#
database = lovd3_development

# This is for the table prefixes; if you wish to install more than one LOVD
# system per database, use different directories for these installations and
# change the setting below to a unique value.
# Please use alphanumeric characters only. Defaults to 'lovd'.
#
table_prefix = lovd_v3

[test]

# Root URL (base URL to reach this installation via a web browser)
# This is only needed for running tests.
root_url = http://localhost/LOVD3


[paths]

# Data paths for submission api
data_files = /home/travis/build/data_files
data_files_archive = /home/travis/build/data_files_archive


EOF

