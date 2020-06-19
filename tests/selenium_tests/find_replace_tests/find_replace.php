<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-07
 * Modified    : 2020-06-19
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

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

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
        // Upload variant data.
        $this->driver->get(ROOT_URL . '/src/variants/upload?create&type=VCF');
        $this->enterValue(WebDriverBy::name("variant_file"), ROOT_PATH .
            "../tests/test_data_files/ShortVCFfilev1.vcf");
        $uploadButton = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Upload VCF file']"));
        $uploadButton->click();

        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/variants');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!preg_match('/167 entries on 2 pages/', $sBody)) {
            $this->markTestSkipped('Not all variants are in place for this test.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
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
        $this->assertEquals(29, count(
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

        // Filter on 'Variant ID' > 100 during preview.
        $this->enterValue('search_id_', '>100');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();

        // Variant ID 150 should now show, it used to be on the second page.
        $this->waitForElement(WebDriverBy::xpath('//table[@class="data"]//tr[@id="0000000150"]'));
        $this->assertEquals(30, count(
            $this->driver->findElements(WebDriverBy::xpath('//td[text()="Author (2020)"]'))));

        $this->enterValue('password', 'test1234');
        $this->submitForm('Submit');

        // Check that filter has effect (otherwise 55 records are modified).
        $this->assertEquals('You are about to modify 30 records. Do you wish to continue?',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();

        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="info" and contains(., "Find & Replace applied to column")]'));
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="Author (2020)"]'));

        // Remove filter.
        $this->enterValue('search_id_', '');
        $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'))->click();

        // The previous value should also still be there, in IDs <= 100.
        $this->waitForElement(WebDriverBy::xpath(
            '//table[@class="data"]//td[text()="Author, submitted"]'));
    }





    /**
     * @depends testEditReferenceWithFilter
     */
    public function testFindReplace()
    {
        // Go to variant overview
        $this->driver->get(ROOT_URL . '/src/variants');

        // Open find and replace for Reference col.
        $this->openFRMenuForCol(6);

        $matchBeginningRadio = $this->driver->findElement(WebDriverBy::xpath(
            '//input[@name="FRMatchType_VOG" and @value="2"]'));
        $matchBeginningRadio->click();
        $this->enterValue(WebDriverBy::name('FRReplace_VOG'), 'prefix');

        // Find empty string at beginning of field and insert prefix string.
        $previewButton = $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'));
        $previewButton->click();

        // Click on tooltip to close it
        $previewTooltip = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and text()="Preview changes (15 rows affected)"]'));
        $previewTooltip->click();

        $this->enterValue(WebDriverBy::xpath('//input[@type="password"]'), 'test1234');
        $submitButton = $this->driver->findElement(WebDriverBy::id('FRSubmit_VOG'));
        $submitButton->click();
        $this->chooseOkOnNextConfirmation();

        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath('//td[text()="prefixnewvalue"]')));
    }
}
