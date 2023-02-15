<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2021-04-21
 * Modified    : 2023-02-14
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
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

        // It's possible that we already have a dialog open, asking us to set
        //  the default license.
        if ($this->isElementPresent(WebDriverBy::id('licenses_dialog'))) {
            $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button'))->click();
            $this->waitForElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Save settings"]'));

            // This is the only way PHPUnit allows us to share data between tests.
            return true; // Skip opening the form.

        } else {
            $this->driver->get(ROOT_URL . '/src');
            $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
            if (preg_match('/LOVD was not installed yet/', $sBody)) {
                $this->markTestSkipped('LOVD was not installed yet.');
            }
            if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
                $this->markTestSkipped('User was not authorized.');
            }

            // This is the only way PHPUnit allows us to share data between tests.
            return false;
        }
    }





    /**
     * @depends testSetUp
     */
    public function testOpenForm ($bSkip)
    {
        if (!$bSkip) {
            $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))->click();

            $this->assertContains('/src/users/0000', $this->driver->getCurrentURL());
            // XPath doesn't accept "Default license", only that it contains
            //  "Default" and that it contains "license".
            $this->driver->findElement(
                WebDriverBy::xpath(
                    '//table[@class="data"]//th[contains(., "Default") and contains(., "license")]/../td/span/a[text()="Change"]'))->click();
            $this->waitForElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Save settings"]'));
        }
    }





    /**
     * @depends testOpenForm
     */
    public function testSubmitForm ()
    {
        // Yes, allow commercial use.
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/form/table[1]/tbody/tr[2]/td/input'))->click();
        // Yes, allow derivatives.
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/form/table[2]/tbody/tr[2]/td/input'))->click();
        // Verify that license has been shown.
        $this->assertContains('Creative Commons Attribution 4.0 International',
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
        $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))->click();

        $this->assertContains('/src/users/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Default") and contains(., "license")]/../td/a[@href="https://creativecommons.org/licenses/by/4.0/"]'));
        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Default") and contains(., "license")]/../td/a/img[@src="gfx/cc_by_80x15.png"]'));

        $this->driver->findElement(
            WebDriverBy::xpath(
                '//table[@class="data"]//th[contains(., "Default") and contains(., "license")]/../td/a/img'))->click();
        $this->waitForElement(WebDriverBy::xpath('//div[@id="licenses_dialog" and contains(., " by default licences using a ")]'));
        $this->driver->findElement(WebDriverBy::xpath('//div[@id="licenses_dialog"]/../div[last()]//button[text()="Close"]'))->click();
    }
}
?>
