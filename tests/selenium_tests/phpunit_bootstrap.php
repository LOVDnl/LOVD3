<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2016-08-23
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Set up global constants and include path for running tests.
define('ROOT_PATH', realpath(__DIR__ . '/../../'));

// Code below to parse the config file is a near copy of that in inc-init.php.
// inc-init.php cannot simply be included here because this code is run from
// the command line. Moving that code to a library is not trivial as it
// produces HTML error messages.
// Fixme: Refactor config file parsing code in inc-init.php to allow usage here
define('CONFIG_URI', ROOT_PATH . '/src/config.ini.php');
if (!$aConfig = file(CONFIG_URI)) {
    throw new Exception('Init', 'Can\'t open config.ini.php');
}

// Parse config file.
$_INI = array();
unset($aConfig[0]); // The first line is the PHP code with the exit() call.

$sKey = '';
foreach ($aConfig as $nLine => $sLine) {
    // Go through the file line by line.
    $sLine = trim($sLine);

    // Empty line or comment.
    if (!$sLine || substr($sLine, 0, 1) == '#') {
        continue;
    }

    // New section.
    if (preg_match('/^\[([A-Z][A-Z_ ]+[A-Z])\]$/i', $sLine, $aRegs)) {
        $sKey = $aRegs[1];
        $_INI[$sKey] = array();
        continue;
    }

    // Setting.
    if (preg_match('/^([A-Z_]+) *=(.*)$/i', $sLine, $aRegs)) {
        list(,$sVar, $sVal) = $aRegs;
        $sVal = trim($sVal, ' "\'“”');

        if (!$sVal) {
            $sVal = false;
        }

        // Set value in array.
        if ($sKey) {
            $_INI[$sKey][$sVar] = $sVal;
        } else {
            $_INI[$sVar] = $sVal;
        }

    } else {
        // Couldn't parse value.
        throw new Exception('Init', 'Error parsing config file at line ' . ($nLine + 1));
    }
}

// Get root URL from config file.
if (!isset($_INI['test']['root_url'])) {
    throw new Exception('Failed to initialize ROOT_URL from ' . CONFIG_URI);
}
define('ROOT_URL', $_INI['test']['root_url']);

// Check if XDebug session should be started.
$bConfigXDebug = isset($_INI['test']['xdebug_enabled']) &&
                 $_INI['test']['xdebug_enabled'] == 'true';
define('XDEBUG_ENABLED', $bConfigXDebug);
$bXDebugStatus = false;


set_include_path(get_include_path() . PATH_SEPARATOR . ROOT_PATH . '/tests/selenium_tests');

use \Facebook\WebDriver\Chrome\ChromeDriver;
use \Facebook\WebDriver\Chrome\ChromeOptions;
use \Facebook\WebDriver\Remote\DesiredCapabilities;
use \Facebook\WebDriver\Remote\WebDriverCapabilityType;
use \Facebook\WebDriver\Remote\RemoteWebDriver;


// Max time for webdriver to wait for a condition by default (in seconds)
define('WEBDRIVER_MAX_WAIT_DEFAULT', 120);

// Interval between webdriver tests for condition during wait (in miliseconds)
define('WEBDRIVER_POLL_INTERVAL_DEFAULT', 1000);

// Time webdriver waits on DOM elements with every call (in seconds)
define('WEBDRIVER_IMPLICIT_WAIT', 30);

// Time to wait when no expected condition can be set (in seconds)
define('SELENIUM_TEST_SLEEP', 10);

function getWebDriverInstance()
{
    // Provide a re-usable webdriver for selenium tests.

    global $_INI;
    static $webDriver;

    if (!isset($webDriver)) {

        $driverType = getenv('LOVD_SELENIUM_DRIVER');
        $host = 'http://localhost:4444/wd/hub';

        if ($driverType == 'chrome') {
            // This is the documented way of starting the chromedriver, but it fails. (at least
            // on my machine with version 2.23)
            // putenv('webdriver.chrome.driver=/usr/share/chromedriver');
            // $webDriver = ChromeDriver::start();

            // Start the chrome driver through the selenium server.
            fwrite(STDERR, 'Connecting to Chrome driver via Selenium at ' . $host);
            $options = new ChromeOptions();
            $options->addArguments(array('--no-sandbox'));
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
            $webDriver = RemoteWebDriver::create($host, $capabilities);
        } else {
            // Create Firefox webdriver
            fwrite(STDERR, 'Connecting to Firefox driver via Selenium at ' . $host);
            $capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
            $webDriver = RemoteWebDriver::create('http://127.0.0.1:4444/wd/hub', $capabilities,
                                                 WEBDRIVER_MAX_WAIT_DEFAULT * 1000,
                                                 WEBDRIVER_MAX_WAIT_DEFAULT * 1000);
        }

        // Set time for trying to access DOM elements
        $webDriver->manage()->timeouts()->implicitlyWait(WEBDRIVER_IMPLICIT_WAIT);

        if (isset($_INI['test']['xdebug_enabled']) && $_INI['test']['xdebug_enabled'] == 'true') {
            // Load page of target host. This is necessary to set a cookie.
            $webDriver->get(ROOT_URL . '/src/');

            // Enable remote debugging by setting XDebug session cookie.
            $webDriver->manage()->addCookie(array(
                'name' => 'XDEBUG_SESSION',
                'value' => 'selenium'));
        }
    }
    return $webDriver;
}
