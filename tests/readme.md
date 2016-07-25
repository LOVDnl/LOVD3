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
[Selenium](http://www.seleniumhq.org/), [PHPUnit](https://phpunit.de/)
and [php-webdriver](https://github.com/facebook/php-webdriver). The 
necessary PHP packages are listed in the `composer.json` file in the 
project's top-level directory. If you have the 
[Composer](https://getcomposer.org/) dependency manager, you can run 
`composer install` from that directory to install the packages. This 
document describes how the LOVD tests are configured and how they can
be used.

## Selenium

The selenium-based tests are located under `tests/selenium_tests/*`.
Originally they were constructed with the 
[Selenium IDE](http://www.seleniumhq.org/projects/ide/) for Selenium
RC (v1). Since then, Selenium RC has become obsolete and the tests have
been upgraded to Selenium Webdriver (v2).

The selenium tests are organized into several large test suites defined
in `phpunit.xml`. The tests are roughly organized in a separate 
directory per suite, with some tests in `shared_tests` re-used in 
multiple suites. **Note: the tests within a suite are typically not
independent, you should treat each test suite as one big test.**


### Running tests

To run the selenium tests, one should have a recent version of the 
[Selenium server](http://www.seleniumhq.org/download/) and the 
[Firefox web browser](http://getfirefox.com). Start the Selenium server
with:

    $ java -jar path/to/selenium/server.jar -trustAllSSLCertificates

A test suite in PHPUnit can then be started with:

    $ phpunit -v --testsuite admin_tests \
      --configuration ./tests/selenium_tests/phpunit.xml

On errors and failing tests, a screenshot of the browser's window will
be taken and written to `tests/test_results/error_screenshots`.

Test-related configuration options can be defined in the 
`config.ini.php` under the header `[test]`. Possible options that can
be defined are:

* `root_url` (mandatory) The base URL to reach the LOVD installation 
  in the web browser.
* `xdebug_enabled` If set to "true", the test runner will attempt to 
  start an xDebug session for the tests.


### Creating / editing tests

The tests should be programmed directly, since the Selenium IDE cannot
be used for the latest version of Selenium and its PHP bindings. The 
test environment is build up in `phpunit_bootstrap.php`. All selenium
tests should extend `LOVDSeleniumWebdriverBaseTestCase`, which also
provides some convenience functions to handle form input and special
situations regarding waiting on events.


The following resources may help writing tests:

* [Selenium webdriver docs (mostly Java)](http://www.seleniumhq.org/docs/03_webdriver.jsp)
* [Tutorial PHPUnit / Selenium webdriver](https://www.sitepoint.com/using-selenium-with-phpunit/)
* [Tutorial Facebook's selenium webdriver (php-webdriver)](https://www.sitepoint.com/using-the-selenium-web-driver-api-with-phpunit/)
* [Facebook's php-webdriver cheat sheet](https://gist.github.com/aczietlow/7c4834f79a7afd920d8f)
* [Facebook's php-webdriver API](http://facebook.github.io/php-webdriver/)



