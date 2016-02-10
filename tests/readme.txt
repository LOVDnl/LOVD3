/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-10
 * Modified    : 2016-02-10
 * For LOVD    : 3.0-15
 *
 *************************/

This is the procedure to make and run test with selenium and phpunit. 

/*
 * Quick start local:
 */
 Run composer install in your project folder.
 Run convert_selenium_to_phpunit.sh --projectfolder="your project folder"
 Run run_phptest.sh to run all tests
 Check "you project folder"/tests/test_results/test_overview.php".
 To see the test results.



/***************************
 * Overview and IMPORTENT remarks
 ***************************/
Make your tests with the Selenium add-on in Firefox. And export them to PHP (phpunit)
You can change the tests in the php files, but you can not convert them back to selenium tests.


Directory structure:
    -tests
	-bash_scripts (contains scripts to convert and run tests.)
	-phpunit_selenium (contains phpunit tests. This folder is empty in github, 
            the files are generated with the convert_selenium_to_phpunit.sh script.)
	-selenium_tests (contains the Selenium test suites, these files you can modify in Selenium.)
	    -setupscript.php (is copied to all new classes)
	    -(folders with testcases. The test cases are the selenium files and 
                the php files. Appart from the extension the exported php should 
                have the same name as the corresponding selenium testcase.)
	-test_data_files (data files used in the tests. For example the lovd-import files)
	-test_results
	    -error_screenshots (screenshots are created and stored here when an error occurs during a test.)
	    -reports (xml phpunit reports are stored here.)
	    -test_overview.php (page with overview of all tests done.)
	    -tests_results.php (page with details on the test results, can only opened via test_overview.php.)
        -travis
            -setup 
                Files necessary for Travis CI
            -tests 
                Tests to test if travis works.
            
Assumptions:
    -The "tests" and "src" folder must both be in a project folder
  
   
/*******************
 * Convert Selenium php files to PHPUnit files
 ************************/
Before you can run the test you must do a conversion, also when no changes are made to the tests. 
The conversion script not only converts selenium php export files to phpunit files, 
but adjust the directory references in the files depending on where you installed you LOVD. 
So, unless your LOVD installation is installed in "www/svn/LOVD3/trunk", 
you must run the script "convert_selenium_to_phpunit.sh" first. 

The convert_selenium_to_phpunit.sh requires input for parameter --projectfolder
This is the project folder relative to the localhost.

Examples local:
    -www/svn/trunk --> --projectfolder=svn/trunk
    -www/svn/LOVD3/trunk --> --projectfolder=svn/LOVD3/trunk
Examples for travis:
    use the only projectname, the githubaccount wil be the root of localhost
    -->  --projectfolder=LOVD3


When everything converted fine, the script will output some overview data (number of test, classes etc).
The errors and warnings which can occur when something is not okey, are self-explanatory.
Fix them and try to convert the again.
When no warnings or error occurred, you can run the script "run_phptest.sh".


/*******************
 * Run the phpunit tests
 ************************/
To run the test user "run_phptest.sh". This script assumes that all dependencies 
are installed with composer.

Use -f=<file> | --file=<file> to test one specific file which is located in the 
phpunit_selenium folder. Specify no file when you want to test all test 
files in the phpunit_selenium folder.

You can see a list of all test run in "../trunk/tests/test_results/test_overview.php".
If you click on the top testlog, you can see the results of the last test. 
If no error or warnings are printed, the test was all fine.

   
/***************************
 * Ceate new or modify test suite
 ***************************/
Make a selenium test suite with the Selenium add-on in Firefox.
This test suite must be stored in ../trunk/tests/selenium_tests
The file name of a test suite must end with "_suite"
    Example:
	-"admin_suite"
	-"manager_suite"
	-"authorization_suite"

The test suite contains selenium tests.
The Selenium test of a test suite are stored in one folder in "trunk/tests/selenium_tests". 
The test folder name is the same as the test suite, but ends with "_tests".
    Example:
	-test suite file: trunk/tests/selenium_tests /admin_suite
	-test folder: trunk/tests/selenium_tests/admin_tests/
	
	-test suite file: trunk/tests/selenium_tests /manager_suite
	-test folder: trunk/tests/selenium_tests/manager_tests/
	
	-test suite file: trunk/tests/selenium_tests /authorization_suite
	-test folder: trunk/tests/selenium_tests/authorization_tests/

Each test suite must begin with the installation of LOVD and end with unistall of LOVD.

Remarks on making tests:
javascript popup screens behave different in selenium and phpunit.
    example selenium tests functions
	chooseOkOnNextConfirmation
	click <"location">
	assertConfirmation <"check text">
	pause "<time to pauze in miliseconds>
    these tests will return an error when done step by step, but they work when run as a test suite.

When a test suite is ready, each test must be converted to a php file.
To do that, go to file -> Export test case as -> PHP (PHPUnit)
Store the php test files with the same selenium test name in the same folder, but with the extension ".php".
For example:
	-Selenium test: /trunk/tests/selenium_tests/admin_tests/create_user_curator
	-Phpunit test: /trunk/tests/selenium_tests/admin_tests/create_user_curator.php

	-Selenium test: /trunk/tests/selenium_tests/admin_tests/add_screening_to_IVA_individual
	-Phpunit test: /trunk/tests/selenium_tests/admin_tests/add_screening_to_IVA_individual.php
If you modify a Selenium test, you have to export the file as well and overwrite the php file when you do an export.

When you converted all selenium test you have to modify the script "convert_selenium_to_phpunit.sh"
    At the top of this script you can see something like:
	testsuitelist[0]='authorization_suite'
	testsuitelist[1]='admin_suite'
	testsuitelist[2]='manager_suite'
	testsuitelist[3]='temp_suite'
Append your new test suite. Don't forget to increase the testsuitelist index.
