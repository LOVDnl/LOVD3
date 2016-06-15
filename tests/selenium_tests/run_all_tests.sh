#!/usr/bin/env bash

# First argument should be the path to a phpunit executable.
PHPUNIT_BIN=$1

# The test suite configuration is expected in phpunit.xml in the same
# directory as this script.
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CONFIG="$DIR/phpunit.xml"

# Run phpunit for every suite.
for SUITE in $( grep -P -o '(?<=name=").+(?=")' $CONFIG ); do
    CMD="$PHPUNIT_BIN -v --configuration $CONFIG --testsuite $SUITE ";
    echo $CMD;
    $CMD;
done
