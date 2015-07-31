#!/bin/bash
# script for creating phpunit selenium tests from the selenium IDE files. See readme file for details.

# This is a array with all folders which must be included in the test.
testsuitelist[0]='temp_suite'
testsuitelist[1]='authorization_suite'
testsuitelist[2]='admin_suite'
testsuitelist[3]='manager_suite'
testsuitelist[4]='curator_suite'
testsuitelist[5]='collaborator_suite'
testsuitelist[6]='submitter_suite'
testsuitelist[7]='import_suite'

# When a php export file is older then a selenium file, the latest changes made in the selenium file might not be included in the php file.
# There for the user is asked what to do. Default is always ask what to do.
alwaysask=true

# As default it is assumed that 'svn' the first localhost folder is. ie http://localhost/svn
# When the first folder is different, it must be given as input.
FIRSTLOCALHOSTFOLDER='svn'

for i in "$@"
do
    case $i in
        -l=*|--localhost=*)
            FIRSTLOCALHOSTFOLDER="${i#*=}"
        ;;
        -c|--continueall)
            alwaysask=false
            # check if input is correct is done later.
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

SCRIPT=$(readlink -f $0)
SCRIPTPATH=$(dirname $SCRIPT)
SELENIUMTESTTPATH=$(dirname $SCRIPTPATH)/selenium_tests
PHPUNITTESTTPATH=$(dirname $SCRIPTPATH)/phpunit_selenium
TESTDATATPATH=$(dirname $SCRIPTPATH)/test_data_files/
##DOCROOT=$(grep -h DocumentRoot /etc/apache2/sites-enabled/*default* | head -n 1 | awk '{print $2};') ##| sed 's\//\\\//g');
##LOCALHOSTDIR=`echo $SCRIPTPATH | sed "s/.*${DOCROOT}//" | sed "s@/trunk.*@@"`
LOCALHOSTDIR=`echo ${SCRIPTPATH} | sed "s@.*$FIRSTLOCALHOSTFOLDER@/$FIRSTLOCALHOSTFOLDER@" | sed "s@/trunk.*@@"`
TRUNKDIR=`echo ${SCRIPT} | sed "s@trunk.*@@"`

# These are used to replace the locations in the setup script.
NEWSETBROWSERURL="http://localhost"${LOCALHOSTDIR}
NEWSCREENSHOTPATH=${TRUNKDIR}"trunk/tests/test_results/error_screenshots"
NEWSCHREENSHOTURL=${NEWSETBROWSERURL}"/trunk/tests/test_results/error_screenshots"

# Used to change de modify date.
DATE=`date +%Y-%m-%d:%H:%M:%S`

# These variables are used to test if the number of test in the test suites corresponds
# with the number of selenium tests, phpunit tests and converted tests.
classescount=0
methodclassescount=0
totalmethod=0
numberoftests=0
totalnumberoftests=0

cd ${SELENIUMTESTTPATH}

# This is a temporary file used to merge all tests to one file.
# This file is deleted at the end of a conversion.
testsuite='TEMP_selenium_suite_test_all'
numbertestsuites=${#testsuitelist[@]}
for (( count=0; count<$numbertestsuites; count++ ))
do
    if [ ! -f "${testsuitelist[$count]}" ]; then
        # One of the files declared in the testsuitelist does not exist.
        echo "ERROR (Line:" $LINENO"): Source testsuite '"${testsuitelist[$count]}"' does not exist."
        echo "WARNING (Line:" $LINENO"): Conversion is aborted!"
        exit
    fi
    if [ $count = 0 ]; then
        # The header of the file is copied
        grep -A 9 "<?xml" ${testsuitelist[$count]}>TEMP_selenium_suite_test_all
    fi
    # The selenium tests are copied to the new temp file.
    grep -A 2000 "href" ${testsuitelist[$count]} | head -n -3 >>TEMP_selenium_suite_test_all
done
# Add the final closing tag to the target file.
echo "</tbody></table>">>TEMP_selenium_suite_test_all
echo "</body>">>TEMP_selenium_suite_test_all
echo "</html>">>TEMP_selenium_suite_test_all

# Get directory names where selenium IDE files are stored.
directories=`grep href TEMP_selenium_suite_test_all | cut -d '"' -f 2 | grep / | cut -d / -f 1 | uniq`

for dir in $directories
do
    echo --------------------------------$dir-------------------------------------------
    files=`ls -1 --ignore="*.php" ${dir}`
    numberfiles=`ls -1 --ignore="*.php" ${dir} | wc -l`
    for file in $files
    do
        # First it is checked if all Selenium IDE files in the folder are exported to a php file.
        # If not all Selenium IDE files are exported, then this directory is skipped.
        if [ ! -f "${dir}/${file}.php" ]; then
            echo "ERROR (Line:" $LINENO"): Source folder '"${dir}"' contains file '"${file}"'."
            echo "But source file '"${dir}/${file}.php"' does not exist."
            echo "creation of class '"$dir"' is interupted!"
            continue 2
        fi
        # The modification date of the php files should be after the Selenium IDE files.
        # This is to check if no modifications are done on the selenium IDE files,
        # which are not exported to the php file.
        moddatephpfile=`stat -c %Y "${dir}/${file}.php"`
        moddateedifile=`stat -c %Y "${dir}/${file}"`
        # A warning is always printed.
        if [ $moddatephpfile -lt $moddateedifile ]; then
            echo "WARNING (Line:" $LINENO"): The Selenium IDE file '"${dir}/${file}"' seems to be modified after the file is exported to '"${dir}/${file}.php"'."
        fi
        # Ask to proceed, if continue all is selected, this question will not asked again.
        if [ $moddatephpfile -lt $moddateedifile ] && [ $alwaysask = true ] ; then
            echo "This can mean that the latest modifications in the the Selenium IDE files are not included in the phpunit tests."
            echo "Are you sure you want to contiue?"
            echo "(c)ontinue, continue (a)ll or (s)top and exit."
            while true
            do
                read input_variable
                case $input_variable in
                     C|c|continue)
                          echo "Continue"
                          break
                          ;;
                     A|a|all)
                          echo "Continue all."
                          alwaysask=false
                          break
                          ;;
                     S|s|stop)
                          echo "STOP!"
                          exit
                          ;;
                     *)
                          echo "enter (c)ontinue, contiue (a)ll or (s)top and exit."
                          ;;
                esac
            done
        fi
    done

    #create class file name
    newfilename=`echo ${dir} | sed 's/_tests/Test/'`

    # copy the setupscript to the new phpunit script. Each original directory will be the name of
    # each new phpunit class file.

    # The setupscript contains a relative path to the folder where the screenshots are stored in case of an error.
    # This relative path should be an absolute pathe.
    screenshotPath=`grep "screenshotPath" setupscript.php | sed "s@Determined on convert@$NEWSCREENSHOTPATH@"`
    screenshotUrl=`grep "screenshotUrl" setupscript.php | sed "s@Determined on convert@$NEWSCHREENSHOTURL@"`
    setBrowserUrl=`grep "setBrowserUrl" setupscript.php | sed "s@Determined on convert@$NEWSETBROWSERURL@"`

    # Here the setupscript is used as an header for each test class.
    # First sed is to change de modify date
    # Second sed is to change the class name
    # Third sed is to change the relative screenshot path to an absolute path.
    grep -A 2000 "<?php" setupscript.php | head -n -2 |
        sed "s/Modified.*/Modified    : "${DATE}/ |
        sed "s/setupscript/"${dir}/ |
        sed "s@.*screenshotPath.*@$screenshotPath@" |
        sed "s@.*screenshotUrl.*@$screenshotUrl@" |
        sed "s@.*setBrowserUrl.*@$setBrowserUrl@">../phpunit_selenium/${newfilename}.php
    echo "create new class file with template setup script and new class name: '"$dir"'"

    # Get the selenium php files from current directory
    files=`grep $dir TEMP_selenium_suite_test_all | cut -d '"' -f 2 | cut -d / -f 2`
    numberoftests=`grep $dir TEMP_selenium_suite_test_all | wc -l`
    totalnumberoftests=$((totalnumberoftests+numberoftests))
    for file in $files
    do
        # Test if source and target files exists. If not then the conversion is interupted.
        if [ ! -f "../phpunit_selenium/${newfilename}.php" ]; then
            echo "ERROR (Line:" $LINENO"): target file '"../phpunit_selenium/${dir}.php"' does not exist."
            echo "WARNING (Line:" $LINENO"): Conversion is interupted!"
            break
        fi
        if [ ! -f "${dir}/${file}.php" ]; then
            echo "ERROR (Line:" $LINENO"): source file '"${dir}/${file}.php"' does not exist."
            echo "WARNING (Line:" $LINENO"): creation of class '"$dir"' is INTERUPTED!"
            break
        fi

        # Get method from source file.
        methodfound=`grep -A 2000 "function testMyTestCase" ${dir}/${file}.php`

        # If no methods are found interupt conversion.
        if [ -z "$methodfound" ]; then
            echo "ERROR (Line:" $LINENO"): no method found in source file '"${dir}/${file}.php"'!"
            echo "possible solution: Make sure to export the selenium test case as a PHP(PHPUnit)!"
            echo "WARNING (Line:" $LINENO"): creation of class '"$dir"' is interupted!"
            break
        fi

        # Use selenium php file name to create new method name.
        # Last sed is to make method name CamelCase.
        method=`echo _${file} | sed 's/_\(.\)/\U\1/g'`

        # Replace original method name with new method name and addust so it is compattible with phpunit.
        # The second "sed" is to adjust indentation to 4 or a multiple of 4.
        # The third "sed" is to change all https in http to avoid security exeptions errors.
        echo "${methodfound}" | head -n -2 | sed "s/MyTestCase/${method}/" |
             sed 's/  /    /g' | sed 's/https/http/g'>>../phpunit_selenium/${newfilename}.php

        ((methodclassescount++))
        ((totalmethod++))
    done
    # Add the final bracket and php closing tag to the target file.
    echo "}">>../phpunit_selenium/${newfilename}.php
    echo "?>">>../phpunit_selenium/${newfilename}.php

    # Test if number of selenium IDE test in the testsuite is the same as the number of test files in the directory.
    if [ ! $numberoftests -eq $methodclassescount ]; then
        echo "WARNING (Line:" $LINENO"): Number of tests in test suite  '"${sourcelist}"' and number of created methods do not correspond!"
    fi
    if [ ! $numberfiles -eq $methodclassescount ]; then
        echo "WARNING (Line:" $LINENO"): Number of tests in the directoty '"${dir}"' and number of created methods do not correspond!"
    fi

    echo "New class '"$dir"' completed"
    echo "Number of methods created in class '"$dir"': "$methodclassescount
    echo
    ((classescount++))
    methodclassescount=0
done
rm TEMP_selenium_suite_test_all
echo ---------------------summary-------------------------
echo "Total number of classes created:" $classescount
echo "Total number of methods converted:" $totalmethod
echo -----------------------end---------------------------


# There are three bugs/issues with the selenium export file.
# 1 The base url is not used. Therefore the directory in the open functions have to be modified.
# 2 In some cases the ";" is not put at the end of a line.
# 3 When files are imported the location must be modified, depending on the installation.
# 4 When files are imported the location must be modified, depending on the installation.
echo --------------Fix Selenium export bugs---------------
for file in "${PHPUNITTESTTPATH}"/*
do
    echo "Fix:" ${file}
    data=`grep -A 2000 "<?php" ${file} |
        sed "s@this->open(\".*./trunk/@this->open(\"$LOCALHOSTDIR/trunk/@" |
        sed 's/0)$/0);/' |
        sed "s@name=variant_file.*./trunk/tests/test_data_files/@name=variant_file\"\, \"$TESTDATATPATH@" |
        sed "s@name=import.*./trunk/tests/test_data_files/@name=import\"\, \"$TESTDATATPATH@"`
    echo "${data}">${file}
    sleep 1
    echo "done"
done
echo ----------------------Fix done-----------------------
exit
