<!---
LEIDEN OPEN VARIATION DATABASE (LOVD)

Created     : 2015-02-10
Modified    : 2016-03-07
For LOVD    : 3.0-15

Authors     : Daan Asscheman <D.Asscheman@LUMC.nl>
            : Mark Kroon <m.kroon@lumc.nl>
-->

# LOVD TEST SUITE

Functional and unit tests for LOVD are implemented using
[Selenium](http://www.seleniumhq.org/) and
[PHPUnit](https://phpunit.de/). This document describes how the LOVD
tests are configured and how they can be used.

## Selenium

Most of the functional tests are based on Selenium. New tests can be
made using the [Selenium IDE](http://www.seleniumhq.org/projects/ide/)
(v1) and subsequently export them to PHPUnit format.

Before running tests, one has to start the Selenium server, which can
be downloaded from the
[download page](http://www.seleniumhq.org/download/) on the Selenium
website. You can start the server with the following command:

    $ java -jar path/to/selenium/server.jar -trustAllSSLCertificates

## PHPUnit

All Selenium-based tests inherit from
`tests/selenium_tests/LOVDSeleniumBaseTestCase`. For PHPUnit and
the Selenium server to know where the LOVD application can be reached
using a web browser, one has to configure a `root_url` in a `[test]`
section in `src/config.ini.php`. For example:

    [test]

    # Root URL (base URL to reach this installation via a web browser)
    # This is only needed for running tests.
    root_url = http://localhost/LOVD3

The test suites are configured in `tests/selenium_tests/phpunit.xml`.
A bootstrap script `tests/selenium_tests/phpunit_bootstrap.php` will
set up an environment for running the selenium tests. PHPUnit will run
the bootstrap script automatically when calling it with the
configuration file, for example:

    $ phpunit --testsuite temp_tests --configuration tests/phpunit_selenium/phpunit.xml

Output from running the tests can be found under `tests/test_results`,
with browser screenshots from failed tests in folder
`error_screenshots` and PHPUnit XML reports in folder `reports`.

