<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-21
 * Modified    : 2024-05-23
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

class SetDefaultLicenseTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.

        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');
    }





    /**
     * @depends testSetUp
     */
    public function testFindSubmission ()
    {
        $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))->click();
        $this->assertStringContainsString('/src/users/0000', $this->driver->getCurrentURL());

        // XPath doesn't accept "Has created", only that it contains
        //  "Has" and that it contains "created".
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Has") and contains(., "created")]/../td/a[contains(., "individual")]'))->click();
        $this->assertStringContainsString('/src/individuals?search_created_by=', $this->driver->getCurrentURL());

        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]/tbody/tr[1]/td[1]'))->click();
    }





    /**
     * @depends testFindSubmission
     */
    public function testOpenForm ()
    {
        $this->waitForURLContains('/src/individuals/0000');
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Database") and contains(., "license")]/../td/span/a[text()="Change"]'))->click();
        $this->waitForElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Save settings"]'));
    }





    /**
     * @depends testOpenForm
     */
    public function testSubmitForm ()
    {
        // No, don't allow commercial use.
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/form/table[1]/tbody/tr[3]/td/input'))->click();
        // Yes, allow derivatives, but only SA.
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/form/table[2]/tbody/tr[3]/td/input'))->click();
        // Verify that license has been shown.
        $this->assertStringContainsString('Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International',
            $this->driver->findElement(WebDriverBy::id('selected_license'))->getText());
        // Submit.
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Save settings"]'))->click();
        $this->waitForElement(WebDriverBy::xpath('//div[text()="Settings saved successfully!"]'));
        // Wait for it to close.
        $this->waitUntil(WebDriverExpectedCondition::invisibilityOfElementLocated(
            WebDriverBy::id('licenses_dialog')));
    }





    /**
     * @depends testSubmitForm
     */
    public function testValidateResults ()
    {
        $this->driver->get($this->driver->getCurrentURL()); // Reload.
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Database") and contains(., "license")]/../td/a[@href="https://creativecommons.org/licenses/by-nc-sa/4.0/"]'));
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Database") and contains(., "license")]/../td/a/img[@src="gfx/cc_by-nc-sa_80x15.png"]'));

        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Database") and contains(., "license")]/../td/a/img'))->click();
        $this->waitForElement(WebDriverBy::xpath('//div[@id="licenses_dialog" and contains(., " is licensed under a ")]'));
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Close"]'))->click();
    }
}
?>
