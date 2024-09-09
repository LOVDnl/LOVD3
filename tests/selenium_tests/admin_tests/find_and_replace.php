<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-07
 * Modified    : 2024-09-06
 * For LOVD    : 3.0-31
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
use \Facebook\WebDriver\WebDriverKeys;

class FindReplaceTest extends LOVDSeleniumWebdriverBaseTestCase
{
    function openFRMenuForCol ($Col)
    {
        // Open the Find & Replace menu for the specified column.
        // $Col can either be a number specifying the column index [1..n] (not recommended)
        //  or the header of the column that should be clicked.
        $this->driver->findElement(WebDriverBy::id('viewlistOptionsButton_VOG'))->click();
        $this->driver->findElement(WebDriverBy::partialLinkText('Find and replace text in column'))->click();

        if (ctype_digit($Col) || is_int($Col)) {
            $sLocator = '//div[@class="vl_overlay"][' . $Col . ']';
        } else {
            // XPath doesn't accept "Variant ID", only that it contains
            //  "Variant" and that it contains "ID".
            $sLocator = '//table[@class="data"]//th[contains(., "' .
                implode('") and contains(., "', explode(' ', $Col)) . '")]';
        }

        $this->driver->getMouse()->click(
            $this->driver->findElement(WebDriverBy::xpath($sLocator))->getCoordinates());

        // Wait a second to handle click event properly and let the tooltip disappear.
        sleep(1);
    }





    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/variants');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!preg_match('/30 entries on 1 page/', $sBody)) {
            $this->markTestSkipped('Not all variants are in place for this test.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');
    }





    /**
     * @depends testSetUp
     */
    public function testVariantIDColumn ()
    {
        // Fail to perform F&R on the Variant ID column.
        $this->driver->get(ROOT_URL . '/src/variants');
        $this->openFRMenuForCol('Variant ID');
        $this->assertEquals('This column is not available.',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();
    }





    /**
     * @depends testVariantIDColumn
     */
    public function testCancel ()
    {
        // Open F&R for the Reference column, and cancel.
        $this->driver->get(ROOT_URL . '/src/variants');
        $this->openFRMenuForCol('Reference');
        $this->driver->findElement(WebDriverBy::id('FRCancel_VOG'))->click();
        $this->waitUntil(WebDriverExpectedCondition::invisibilityOfElementLocated(
            WebDriverBy::id('FRCancel_VOG')));
        // To prevent a Risky test, we have to do at least one assertion.
        $this->assertEquals('', '');
    }





    /**
     * @depends testCancel
     */
    public function testAddReferenceIfEmpty ()
    {
        // Perform F&R on the Reference column, and fill in a new value for all empty fields.
        $this->driver->get(ROOT_URL . '/src/variants');
        $this->openFRMenuForCol('Reference');
        $this->assertEquals('Reference', $this->driver->findElement(
            WebDriverBy::id('viewlistFRColDisplay_VOG'))->getText());
        $this->enterValue('FRReplace_VOG', 'Author, submitted');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();

        // Click on header to close tooltip.
        $oPreviewTooltip = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and contains(., "Preview changes")]'));
        $this->driver->findElement(WebDriverBy::xpath('//h2[contains(., "LOVD")]'))->click();
        $this->waitUntil(WebDriverExpectedCondition::stalenessOf($oPreviewTooltip));

        $this->assertEquals('Reference (PREVIEW)', $this->driver->findElement(
            WebDriverBy::xpath('//th[@data-fieldname="VariantOnGenome/Reference_FR"]'))->getText());
        $this->assertEquals(27, count(
            $this->driver->findElements(WebDriverBy::xpath('//td[text()="Author, submitted"]'))));

        $this->enterValue('password', 'test1234');
        $this->submitForm('Submit');
        $this->chooseOkOnNextConfirmation();
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="info" and contains(., "Find & Replace applied to column")]'));
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="Author, submitted"]'));
    }





    /**
     * @depends testAddReferenceIfEmpty
     */
    public function testEditReferenceWithFilter ()
    {
        // Perform F&R on the Reference column, and replace part of some fields.
        $this->driver->get(ROOT_URL . '/src/variants');
        $this->openFRMenuForCol('Reference');
        $this->assertEquals('Reference', $this->driver->findElement(
            WebDriverBy::id('viewlistFRColDisplay_VOG'))->getText());
        $this->enterValue('FRSearch_VOG', ', submitted');
        $this->enterValue('FRReplace_VOG', ' (2020)');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();

        // Click on tooltip to close it.
        $oPreviewTooltip = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and contains(., "Preview changes")]'));
        $oPreviewTooltip->click();
        $this->waitUntil(WebDriverExpectedCondition::stalenessOf($oPreviewTooltip));

        // Filter on 'Variant ID' > 10 during preview.
        $this->enterValue('search_id_', '>10');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();
        $nExpected = 20;
        // Except for when you're Chrome, then you report there are 27, even though xpath indicates there are only 20.
        // So why Chrome refuses to acknowledge there are only 20, nobody knows. But this bug is consistent.
        if (getenv('LOVD_SELENIUM_DRIVER') == 'chrome') {
            $nExpected = 27;
        }
        $this->assertEquals($nExpected, count(
            $this->driver->findElements(WebDriverBy::xpath('//td[text()="Author (2020)"]'))));

        $this->enterValue('password', 'test1234');
        $this->submitForm('Submit');

        // Check that filter had effect (otherwise 27 records are modified).
        $this->assertEquals('You are about to modify 20 records. Do you wish to continue?',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();

        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="info" and contains(., "Find & Replace applied to column")]'));
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="Author (2020)"]'));

        // Remove filter.
        $this->enterValue('search_id_', WebDriverKeys::ENTER);

        // The previous value should also still be there, in IDs <= 10.
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="Author, submitted"]'));
    }





    /**
     * @depends testEditReferenceWithFilter
     */
    public function testEditReferenceAddPrefixWithFilter ()
    {
        // Perform F&R on the Reference column, and add a prefix to some fields.
        $this->driver->get(ROOT_URL . '/src/variants');
        $this->openFRMenuForCol('Reference');
        $this->assertEquals('Reference', $this->driver->findElement(
            WebDriverBy::id('viewlistFRColDisplay_VOG'))->getText());
        $this->driver->findElement(WebDriverBy::xpath(
            '//input[@name="FRMatchType_VOG" and @value="2"]'))->click();
        $this->enterValue('FRReplace_VOG', 'First ');
        $this->enterValue('search_VariantOnGenome/Reference', 'Author');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();

        // Click on tooltip to close it.
        $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and text()="Preview changes (27 rows affected)"]'))->click();

        $this->enterValue('password', 'test1234');
        $this->submitForm('Submit');

        // Check that filter has effect (otherwise 30 records are modified).
        $this->assertEquals('You are about to modify 27 records. Do you wish to continue?',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();

        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="info" and contains(., "Find & Replace applied to column")]'));
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="First Author (2020)"]'));
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="First Author, submitted"]'));
    }
}
?>
