#!/usr/bin/env bash

phpVersion=`php -v`;
echo "PHP version: ${phpVersion}";

echo "Turning off xDebug.";
phpenv config-rm xdebug.ini;

echo "Installing dependencies with composer";
composer install;
