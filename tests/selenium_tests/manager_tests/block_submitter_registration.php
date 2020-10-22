<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-31
 * Modified    : 2020-10-08
 * For LOVD    : 3.0-25
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
        $this->waitForURLEndsWith('/src/setup');
    }





    /**
     * @depends testTurnSettingOff
     */
    public function testSetting ()
    {
        // Instead of logging out and back in, open a new private window.
        // FF needs Ctrl-Shift-P, Chrome needs Ctrl-Shift-N.
        // Lots of methods have been documented on how to do this, but
        //  nothing currently works.
        // See https://github.com/php-webdriver/php-webdriver/issues/226
        //  for an example on how it's documented to work.
        // See https://github.com/php-webdriver/php-webdriver/issues/796
        //  for my issue, indicating that none of the options work.
        ////////////////////////////////////////////////////////////////////////
        // $this->driver->sendKeys(
        //     array(
        //         WebDriverKeys::CONTROL,
        //         WebDriverKeys::SHIFT,
        //         (getenv('LOVD_SELENIUM_DRIVER') == 'firefox'? 'p' : 'n')
        //     ));
        ////////////////////////////////////////////////////////////////////////

        // Log out, then check if element is gone indeed.
        $this->logout();

        $this->assertNotContains('Register as submitter',
            $this->driver->findElement(WebDriverBy::xpath(
                '//table[@class="logo"]//td[contains(., "LOVD v.3.0")]'))->getText());

        // Not only the link should be gone. Also the form should no longer work.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="info"]//td[contains(text(), "Submitter registration is not active in this LOVD installation.")]'));
    }





    /**
     * @depends testSetting
     */
    public function testTurnSettingOn ()
    {
        // Log in as a manager again, and enable the feature again.
        $this->login('manager', 'test1234');

        $this->driver->get(ROOT_URL . '/src/settings?edit');
        $this->check('allow_submitter_registration');
        $this->submitForm('Edit system settings');
        $this->chooseOkOnNextConfirmation();
        $this->assertEquals('Successfully edited the system settings!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForURLEndsWith('/src/setup');
    }





    /**
     * @depends testTurnSettingOn
     */
    public function testSettingAgain ()
    {
        // Log out, and check if registration is allowed again.
        $this->logout();

        $this->assertContains('Register as submitter',
            $this->driver->findElement(WebDriverBy::xpath(
                '//table[@class="logo"]//td[contains(., "LOVD v.3.0")]'))->getText());

        // Also verify the form works again.
        $this->driver->get(ROOT_URL . '/src/users?register');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="dataform"]//td[text()="Please enter your ORCID ID"]'));

        // Log back in, to leave the state in the way that we found it.
        $this->login('manager', 'test1234');
    }
}
?>
