<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-07
 * Modified    : 2020-06-18
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
use \Facebook\WebDriver\Exception\StaleElementReferenceException;

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
    public function testFindReplace()
    {
        // Go to variant overview
        $this->driver->get(ROOT_URL . '/src/variants');

        // Open find and replace for Reference col.
        $this->openFRMenuForCol(6);

        // Click cancel button.
        sleep(1);
        $cancelButton = $this->driver->findElement(WebDriverBy::id('FRCancel_VOG'));
        $cancelButton->click();

        // Check if cancel button is hidden together with FR options menu by javascript.
        $this->waitUntil(WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::id('FRCancel_VOG')));

        // Open find and replace for Reference col.
        $this->openFRMenuForCol(6);

        $columnReference = $this->driver->findElement(WebDriverBy::id('viewlistFRColDisplay_VOG'));
        $this->assertEquals($columnReference->getText(), 'Reference');

        $this->enterValue(WebDriverBy::name('FRReplace_VOG'), 'newvalue');

        $previewButton = $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'));
        $previewButton->click();

        // Click on header to close tooltip.
        $previewTooltip = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and contains(., "Preview changes")]'));
        $mainHeader = $this->driver->findElement(WebDriverBy::xpath('//h2[contains(., "LOVD")]'));
        $mainHeader->click();

        // Check if tooltip is closed.
        $this->waitUntil(WebDriverExpectedCondition::stalenessOf($previewTooltip));

        $this->assertContains($this->driver->findElement(
            WebDriverBy::xpath('//th[@data-fieldname="VariantOnGenome/Reference_FR"]'))->getText(), 'Reference (PREVIEW)');

        $aNewValueElements = $this->driver->findElements(WebDriverBy::xpath('//td[text()="newvalue"]'));
        $this->assertEquals(count($aNewValueElements), 25);

        $this->enterValue(WebDriverBy::xpath('//input[@type="password"]'), 'test1234');
        $submitButton = $this->driver->findElement(WebDriverBy::id('FRSubmit_VOG'));
        $submitButton->click();

        $this->chooseOkOnNextConfirmation();

        // Wait for refresh of viewlist with string "newvalue" in 6th column (Reference).
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath('//table[@id="viewlistTable_VOG"]/tbody/tr/td[position()=6 and text()="newvalue"]')));

        // Open find & replace menu for field "DNA change (genomic) (hg19)"
        $this->openFRMenuForCol(5);

        // Set search field to 'C' and preview.
        $this->enterValue(WebDriverBy::name('FRSearch_VOG'), 'C');
        $previewButton = $this->driver->findElement(WebDriverBy::id('FRPreview_VOG'));
        $previewButton->click();

        // Click on tooltip to close it
        $previewTooltip = $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="ui-tooltip-content" and text()="Preview changes (14 rows affected)"]'));
        $previewTooltip->click();

        // Filter on 'Variant ID' > 10 during preview.
        $sSearchIDSelector = WebDriverBy::name('search_id_');
        $this->enterValue($sSearchIDSelector, '>10');
        $searchIDElement = $this->driver->findElement($sSearchIDSelector);
        // Use json_decode to send enter key to browser.
        $searchIDElement->sendKeys(json_decode('"\uE007"'));

        // Check that viewlist is refreshed. (id='0000000004' should be filtered)
        $this->waitUntil(function ($driver) {
            $vlTable = $driver->findElement(WebDriverBy::id('viewlistTable_VOG'));
            // Avoid checking text exactly during refresh.
            try {
                $sTableText = $vlTable->getText();
            } catch (StaleElementReferenceException $e) {
                // try again next poll
                return false;
            }
            return strpos($sTableText, '0000000004') === false;
        });

        // Submit find & replace
        $this->enterValue(WebDriverBy::xpath('//input[@type="password"]'), 'test1234');
        $submitButton = $this->driver->findElement(WebDriverBy::id('FRSubmit_VOG'));
        $submitButton->click();

        // Check that filter has effect (otherwise 14 records are modified).
        $alertText = $this->driver->switchTo()->alert()->getText();
        $this->assertContains('You are about to modify 9 records', $alertText);

        $this->chooseOkOnNextConfirmation();

        // Wait until viewlist is refreshed.
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath(
            '//td[@valign="middle" and contains(., "Find & Replace applied to column")]')));

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
