#!/bin/bash

## This user/login information here should be in accordance with setup_config.sh
echo "Setup mysql"
mysql -e "create database IF NOT EXISTS lovd3_development;" -u root
mysql -e "CREATE USER 'lovd'@'localhost' IDENTIFIED BY 'lovd_pw';" -u root
mysql -e "GRANT ALL PRIVILEGES ON * . * TO 'lovd'@'localhost';" -u root
mysql -e "FLUSH PRIVILEGES;" -u root