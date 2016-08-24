<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-13
 * Modified    : 2016-07-18
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



use \Facebook\WebDriver\Remote\WebDriverCapabilityType;
use \Facebook\WebDriver\Remote\RemoteWebDriver;


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






function setMutalyzerServiceURL($sURL)
{
    // Set up the LOVD environment with all common globals like a database
    // connection, configuration settings, etc. by including inc-init.php.
    define('FORMAT_ALLOW_TEXTPLAIN', true);
    $_GET['format'] = 'text/plain';
    // To prevent notices when running inc-init.php.
    $_SERVER = array_merge($_SERVER, array(
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => '/' . basename(__FILE__),
        'QUERY_STRING' => '',
        'REQUEST_METHOD' => 'GET',
    ));
    require_once ROOT_PATH . 'inc-init.php';

    $result = $_DB->query('UPDATE ' . TABLE_CONFIG . ' SET mutalyzer_soap_url=?', array($sURL));

    // Return true if query was executed successfully
    return $result !== false;
}

