/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-10
 * Modified    : 2015-02-17
 * For LOVD    : 3.0-13
 *
 *************************/

This is the procedure to make and run test with selenium and phpunit. 

/***************************
 * Overview and IMPORTENT remarks
 ***************************/
Make your tests with the Selenium add-on in Firefox..
Modify the tests only in the Selenium add-on in Firefox and not in the php files. 

Directory structure:
    -tests
	-bash_scripts (contains scripts to convert and run tests.)
	-phpunit_selenium (contains phpunit tests. Do not modify these files, the are generated.)
	-selenium_tests (contains the Selenium test suites, these files you can modify in Selenium.)
	    -setupscript.php (is copied to all new classes)
	    -(folders with test of the testsuites.)
	-test_data_files (data files used in the tests.)
	-test_results
	    -error_screenshots (screenshots are created and stored here when an error occurs during a test.)
	    -reports (xml phpunit reports are stored here.)
	    -test_overview.php (page with overview of all tests done.)
	    -tests_results.php (page with details on the test results, can only opened via test_overview.php.)
   
Assumptions:
    -The "tests" and "src" folder must both be in the folder "trunk"
    -"svn" must be the highest internet folder, you can do this with a link.
	Examples
	    The following directory structures are fine:
	    -www/svn/trunk
	    -www/svn/LOVD3/trunk
	    -www/svn/LOVD3test/trunk
	    -home/user/svn/trunk (with a soft link of svn to www.)
  
   
/*******************
 * Convert Selenium php files to PHPUnit files
 ************************/
Before you can run the test you must do a conversion, also when no changes are made to the tests. 
The conversion script not only converts selenium php export files to phpunit files, but adjust the directory references in the files depending on where you installed you LOVD. So, unless your LOVD installation is installed in "www/svn/LOVD3/trunk", you must run the script "convert_selenium_to_phpunit.sh" first. 

When everything converted fine, the script will output some overview data (number of test, classes etc).
The errors and warnings which can occur when something is not okey, are self-explanatory.
Fix them and try to convert the again.
When no warnings or error occurred, you can run the script "run_phptest.sh".


/*******************
 * Run the phpunit tests
 ************************/
 
To run the test phpunit and selenium (version 2.44.0) are required. see https://phpunit.de/manual/current/en/selenium.html
The selenium server must be installed some where in a bin folder: /bin/selenium-server-standalone-2.44.0.jar

To run "run_phptest.sh"
Use -f=<file> | --file=<file> to test one specific file which is located in the phpunit_selenium folder. Specify no file when you want to test all test files in the phpunit_selenium folder.
Use -p=<folder> | --phpunit=<folder> to define the phpunit folder when you can not call phpunit directly.

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
