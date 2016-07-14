<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2016-08-11
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
define('ROOT_PATH', realpath(__DIR__ . '/../../src') . '/');

require_once ROOT_PATH . 'inc-lib-init.php';

// Get configuration settings.
define('CONFIG_URI', ROOT_PATH . 'config.ini.php');
if (!$aConfig = file(CONFIG_URI)) {
    throw new Exception('Init', 'Can\'t open config.ini.php');
}
$_INI = lovd_parseConfigFile(CONFIG_URI);

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
        // Create Firefox webdriver
        $capabilities = array(WebDriverCapabilityType::BROWSER_NAME => 'firefox');
        $webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities,
                                             WEBDRIVER_MAX_WAIT_DEFAULT * 1000,
                                             WEBDRIVER_MAX_WAIT_DEFAULT * 1000);

        // Set time for trying to access DOM elements
        $webDriver->manage()->timeouts()->implicitlyWait(WEBDRIVER_IMPLICIT_WAIT);

        if (isset($_INI['test']['xdebug_enabled']) && $_INI['test']['xdebug_enabled'] == 'true') {
            // Enable remote debugging by setting XDebug session cookie.
            $webDriver->manage()->addCookie(array(
                'name' => 'XDEBUG_SESSION',
                'value' => 'selenium'));
        }
    }
    return $webDriver;
}
