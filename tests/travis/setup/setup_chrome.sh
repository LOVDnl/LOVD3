#!/usr/bin/env bash

# Installation of chrome on Travis' trusty machines.
# Based on: http://blog.500tech.com/setting-up-travis-ci-to-run-tests-on-latest-google-chrome-version/
export DISPLAY=:99.0
sh -e /etc/init.d/xvfb start

# Fixme: uncomment lines below to use latest stable Chrome browser for testing
export CHROME_BIN=/usr/bin/google-chrome
sudo apt-get update
wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo dpkg -i google-chrome*.deb
