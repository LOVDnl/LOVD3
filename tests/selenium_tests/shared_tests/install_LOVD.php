<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2024-05-23
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class InstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // Checks if we're currently installed. If so, uninstalls LOVD first.
        // This is needed because test suites may be started when the previous
        //  one did not complete. However, test suites need to be independent
        //  so LOVD still needs to be freshly installed for this test suite.
        $this->driver->get(ROOT_URL . '/src/install');

        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');

        $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
        if (preg_match('/This installer will create/', $bodyElement->getText())) {
            // Not installed already, all good!
            return true;
        }

        // OK, we're installed already. Uninstall first.
        require_once dirname(__FILE__) . '/uninstall_LOVD.php';
        $Test = new UninstallLOVDTest();
        $Test->setUp(); // Make sure it gets the driver info.
        $Test->test();
        return true;
    }





    /**
     * @depends testSetUp
     */
    public function testInstallLOVD ()
    {
        // Check if an XDebug session needs to be started, and if so, add the
        // XDebug get parameter.
        // Note: this has to be done once per session. Starting XDebug in
        //       setUp() is not possible as the session may not have
        //       initialized yet. The current method is the common starting
        //       point for most selenium tests.
        global $bXDebugStatus;
        if (XDEBUG_ENABLED && isset($bXDebugStatus) && !$bXDebugStatus) {
            $this->driver->get(ROOT_URL . '/src/install?XDEBUG_SESSION_START=test');
            $bXDebugStatus = true;
        } else {
            $this->driver->get(ROOT_URL . '/src/install');
        }

        $bodyElement = $this->driver->findElement(WebDriverBy::tagName('body'));
        $this->assertStringContainsString('This installer will create', $bodyElement->getText());

        // Start installation procedure.
        $this->submitForm('Start');

        // Fill out Administrator form.
        $this->waitForURLEndsWith('/src/install/?step=1');
        $this->enterValue('name', 'LOVD3 Admin');
        $this->enterValue('institute', 'Leiden University Medical Center');
        $this->enterValue('department', 'Human Genetics');
        $this->enterValue('address', "Einthovenweg 20\n2333 ZC Leiden");
        $this->enterValue('email', 'test@lovd.nl');
        $this->enterValue('telephone', '+31 (0)71 526 9438');
        $this->enterValue('username', 'admin');
        $this->enterValue('password_1', 'test1234');
        $this->enterValue('password_2', 'test1234');
        $this->selectValue('countryid', 'Netherlands');
        $this->enterValue('city', 'Leiden');
        $this->submitForm('Continue');

        // Confirmation of account information, installing...
        $this->waitForURLEndsWith('/src/install/?step=1&sent=true');
        // We'll need to clean the cookies, so we'll be able to get the LOVD's ID from there.
        $aInitialSessionIDs = $this->getAllSessionIDs();
        $this->submitForm('Next');

        // Installation complete.
        $this->waitForURLEndsWith('/src/install/?step=2');
        $aSessionIDsInCommon = array_intersect($this->getAllSessionIDs(), $aInitialSessionIDs);
        foreach ($aSessionIDsInCommon as $sSessionID) {
            $this->driver->manage()->deleteCookieNamed('PHPSESSID_' . $sSessionID);
        }
        $this->submitForm('Next');
    }





    /**
     * @depends testInstallLOVD
     */
    public function testSetSettings ()
    {
        // Fill out System Settings form.
        $this->waitForURLEndsWith('/src/install/?step=3');
        $this->enterValue('institute', 'Leiden University Medical Center');
        $this->enterValue('email_address', 'noreply@LOVD.nl');
        $this->selectValue('refseq_build', 'hg19');
        $this->unCheck('send_stats');
        $this->unCheck('include_in_listing');
        $this->unCheck('lock_uninstall');
        $this->submitForm('Continue');

        // Settings stored.
        $this->waitForURLEndsWith('/src/install/?step=3&sent=true');
        $this->submitForm('Next');

        // Done!
        $this->waitForURLEndsWith('/src/install/?step=4');
        $this->clickButton('Continue to Setup area');

        // LOVD Setup Area.
        $this->waitForURLEndsWith('/src/setup?newly_installed');
        $this->assertStringContainsString('General LOVD Setup', $this->driver->findElement(WebDriverBy::tagName('body'))->getText());
    }
}
?>
