<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-13
 * Modified    : 2020-05-21
 * For LOVD    : 3.0-23
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





function getLOVDGlobals()
{
    // Get common global variables from the LOVD environment that LOVD usually
    //  generates in inc-init.php for a normal web request.
    // Run this ONLY when LOVD is installed; otherwise you'll get an incomplete
    //  $status and you'll never be able to fill that properly again.
    // FIXME: Retire this function if you can,
    //  I don't like hacking into the LOVD instance like this.
    //  There should be a way around using it.
    static $db, $status;
    if (!isset($db)) {
        // Settings and constants to prevent notices when including inc-init.php.
        define('FORMAT_ALLOW_TEXTPLAIN', true);
        $_GET['format'] = 'text/plain';
        $_SERVER = array_merge($_SERVER, array(
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => '/' . basename(__FILE__),
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => 'GET',
        ));
        require_once ROOT_PATH . 'inc-init.php';

        // Save interesting global variables.
        $db = $_DB;
        $status = $_STAT;
    }
    return array($db, $status);
}





function setMutalyzerServiceURL ($sURL)
{
    // Set the Mutalyzer URL in the database to the TEST server, as agreed with
    //  the Mutalyzer team. This way, one test run of LOVD also tests their
    //  update.
    list($db,) = getLOVDGlobals();

    if (defined('NOT_INSTALLED')) {
        // Just return true here. Don't fail on not being able to set this URL.
        return true;
    }

    $result = $db->query('UPDATE ' . TABLE_CONFIG . ' SET mutalyzer_soap_url=?', array($sURL));

    // Return true if query was executed successfully
    return $result !== false;
}

