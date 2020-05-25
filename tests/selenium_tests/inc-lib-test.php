<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-13
 * Modified    : 2020-05-25
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'LOVDWebDriver.php';

use \Facebook\WebDriver\Chrome\ChromeOptions;
use \Facebook\WebDriver\Firefox\FirefoxDriver;
use \Facebook\WebDriver\Firefox\FirefoxProfile;
use \Facebook\WebDriver\Remote\DesiredCapabilities;





function getWebDriverInstance ()
{
    // Provide a re-usable webdriver for selenium tests.

    global $_INI;
    static $webDriver;

    if (!isset($webDriver)) {

        $driverType = getenv('LOVD_SELENIUM_DRIVER');
        $host = 'http://localhost:4444/wd/hub';
        $capabilities = null;

        if ($driverType == 'chrome') {
            // Start the chrome driver through the selenium server.
            fwrite(STDERR, 'Connecting to Chrome driver via Selenium at ' . $host . PHP_EOL);
            $options = new ChromeOptions();
            $options->addArguments(array('--no-sandbox'));
            $options->setExperimentalOption('prefs', array(
                'download.default_directory' => '/tmp/',
            ));
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        } else {
            // Create Firefox webdriver.
            fwrite(STDERR, 'Connecting to Firefox driver via Selenium at ' . $host . PHP_EOL);
            $profile = new FirefoxProfile();
            $profile->setPreference('browser.download.folderList', 2);
            $profile->setPreference('browser.download.dir', '/tmp/');
            $profile->setPreference('browser.helperApps.neverAsk.saveToDisk', 'text/plain');
            $capabilities = DesiredCapabilities::firefox();
            $capabilities->setCapability(FirefoxDriver::PROFILE, $profile);
        }

        $webDriver = LOVDWebDriver::create($host, $capabilities,
            WEBDRIVER_MAX_WAIT_DEFAULT * 1000,
            WEBDRIVER_MAX_WAIT_DEFAULT * 1000);

        // Set time for trying to access DOM elements
        // This keeps failing. No clue why. Both Chrome and FF don't like it, although Chrome seems to handle it on the Travis environment.
        // $webDriver->manage()->timeouts()->implicitlyWait(WEBDRIVER_IMPLICIT_WAIT);

        if (isset($_INI['test']['xdebug_enabled']) && $_INI['test']['xdebug_enabled'] == 'true') {
            // Load page of target host. This is necessary to set a cookie.
            $webDriver->get(ROOT_URL . '/src/');

            // Enable remote debugging by setting XDebug session cookie.
            $webDriver->manage()->addCookie(array(
                'name' => 'XDEBUG_SESSION',
                'value' => 'selenium'));
        }
    }

    // Wrap the webdriver instance in a custom processor.
    return $webDriver;
}
?>
