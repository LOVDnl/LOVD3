<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2017-08-21
 * For LOVD    : 3.0-20
 *
 * Copyright   : 2014-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
define('ROOT_PATH', realpath(__DIR__ . '/../src') . '/');
define('LOVD_plus', false);

require_once ROOT_PATH . 'inc-lib-init.php';

// Get configuration settings.
$_INI = lovd_parseConfigFile(ROOT_PATH . 'config.ini.php');

// Get root URL from config file.
if (!isset($_INI['test']['root_url'])) {
    fputs(STDERR, 'Warning: failed to initialize ROOT_URL from ' . ROOT_PATH . 'config.ini.php' .
        PHP_EOL);
} else {
    define('ROOT_URL', $_INI['test']['root_url']);
}

// Check if XDebug session should be started.
$bConfigXDebug = isset($_INI['test']['xdebug_enabled']) &&
                 $_INI['test']['xdebug_enabled'] == 'true';
define('XDEBUG_ENABLED', $bConfigXDebug);
$bXDebugStatus = false;

// Additions to the include path specifically for files needed in tests.
set_include_path(get_include_path() . PATH_SEPARATOR .
    ROOT_PATH . PATH_SEPARATOR .
    ROOT_PATH . '../tests/selenium_tests');

// Max time for webdriver to wait for a condition by default (in seconds)
define('WEBDRIVER_MAX_WAIT_DEFAULT', 120);

// Interval between webdriver tests for condition during wait (in miliseconds)
define('WEBDRIVER_POLL_INTERVAL_DEFAULT', 1000);

// Time webdriver waits on DOM elements with every call (in seconds)
define('WEBDRIVER_IMPLICIT_WAIT', 30);

// Time to wait when no expected condition can be set (in seconds)
define('SELENIUM_TEST_SLEEP', 10);

// Maximum number of tries to set a checkbox (sometimes click events fail to
// check/uncheck a box the first time.
define('MAX_TRIES_CHECKING_BOX', 10);

// Maximum number of tries to refresh an element after a
// StaleElementReferenceException has been thrown.
define('MAX_TRIES_STALE_REFRESH', 10);

