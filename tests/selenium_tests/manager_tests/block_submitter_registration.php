<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-31
 * Modified    : 2020-06-04
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

class BlockSubmitterRegistrationTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        $this->driver->get(ROOT_URL . '/src/settings?edit');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area, you need at least/', $sBody)) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testTurnSettingOff ()
    {
        $this->driver->get(ROOT_URL . '/src/settings?edit');
        $this->unCheck('allow_submitter_registration');
        $this->submitForm('Edit system settings');
        $this->chooseOkOnNextConfirmation();
        $this->assertEquals('Successfully edited the system settings!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/setup'));
    }





    /**
     * @depends testTurnSettingOff
     */
    public function testSetting ()
    {
        // Log out, then check if element is gone indeed.
        $this->logout();

        // There should be no link to register yourself.
        // First, I had this findElements(), but Chrome doesn't like that at all, and times out.
        // Firefox anyway took quite some time, because of the timeout that we have set if elements are not found immediately (normally needed if pages load slowly).
        // $this->assertFalse((bool) count($this->driver->findElements(WebDriverBy::xpath('//a/b[text()="Register as submitter"]'))));
        // New attempt to test for absence of register link.
        $this->assertFalse(strpos($this->driver->findElement(WebDriverBy::xpath('//table[@class="logo"]//td[3]'))->getText(), 'Register as submitter'));

        // Not only the link should be gone. Also the form should no longer work.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"]//td[contains(text(), "Submitter registration is not active in this LOVD installation.")]'));

        // Then, log in as a manager again, and enable the feature again. Then test again.
        $this->login('manager', 'test1234');

        // Change the setting back.
        $this->driver->get(ROOT_URL . '/src/settings?edit');
        $this->setCheckBoxValue(WebDriverBy::name('allow_submitter_registration'), true);
        $element = $this->driver->findElement(WebDriverBy::xpath('//input[@type="submit"]'));
        $element->click();
        $this->chooseOkOnNextConfirmation();

        // Log out, and check if registration is allowed again.
        $this->logout();

        // Find the link to register yourself.
        $this->driver->findElement(WebDriverBy::xpath('//a/b[text()="Register as submitter"]'));

        // Also verify the form still works.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath('//input[contains(@value, "I don\'t have an ORCID ID")]'));

        // Log back in, future tests may need it.
        $this->login('manager', 'test1234');
    }
}
