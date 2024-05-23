<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
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

use \Facebook\WebDriver\Exception\StaleElementReferenceException;
use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;
use \Facebook\WebDriver\WebDriverKeys;

class CreateSummaryVariantLocatedWithinGeneWithMultipleTranscriptsTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/genes/ARSD');
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
    public function testNavigateMenu ()
    {
        $this->driver->get(ROOT_URL . '/src/submit');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "No, I will only submit summary variant data")]'))->click();
        $this->assertStringStartsWith('Please reconsider to submit individual data as well, as it makes the data you submit much more valuable!',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();
        $this->waitForURLEndsWith('/src/variants?create');

        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "A variant that is located within a gene")]'))->click();
        // We probably don't need to search for ARSD, but we might as well.
        try {
            // Travis' Chrome keeps failing here with a StaleElementReferenceException without refreshes.
            $this->enterValue('search_id_', 'ARSD' . WebDriverKeys::ENTER);
        } catch (StaleElementReferenceException $e) {}
        $this->driver->findElement(WebDriverBy::xpath('//tr[@id="ARSD"]/td[1]'))->click();
    }





    /**
     * @depends testNavigateMenu
     */
    public function testCreateVariantWithinARSD ()
    {
        $this->waitForURLEndsWith('/src/variants?create&reference=Transcript&geneid=ARSD');
        // We'll be using transcript IDs 2 and 3, while 4 and 5 will be ignored.
        $this->check('ignore_00000004');
        $this->check('ignore_00000005');
        $this->assertTrue($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000002_VariantOnTranscript/DNA"]'))->isEnabled());
        $this->assertTrue($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000003_VariantOnTranscript/DNA"]'))->isEnabled());
        $this->assertFalse($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000004_VariantOnTranscript/DNA"]'))->isEnabled());
        $this->assertFalse($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000005_VariantOnTranscript/DNA"]'))->isEnabled());

        // Fill in NM_001669.3 transcript (ID #00000002).
        $this->enterValue('00000002_VariantOnTranscript/Exon', '7');
        $this->enterValue('00000002_VariantOnTranscript/DNA', 'c.1100A>T');
        $this->driver->findElement(WebDriverBy::cssSelector('button.mapVariant'))->click();

        // Wait until RNA description field is filled after AJAX request, and check all values.
        $this->waitForValueContains('00000002_VariantOnTranscript/RNA', 'r.');
        $this->assertValue('r.(?)', '00000002_VariantOnTranscript/RNA');
        $this->assertValue('p.(His367Leu)', '00000002_VariantOnTranscript/Protein');
        $this->selectValue('00000002_effect_reported', 'Effect unknown');
        $this->selectValue('00000002_effect_concluded', 'Not classified');

        // Fill in XM_005274514.1 transcript (ID #00000003).
        $this->enterValue('00000003_VariantOnTranscript/Exon', '6i');
        $this->waitForValueContains('00000003_VariantOnTranscript/RNA', 'r.');
        $this->assertValue('c.1001-715A>T', '00000003_VariantOnTranscript/DNA');
        $this->assertValue('r.(=)', '00000003_VariantOnTranscript/RNA');
        $this->assertValue('p.(=)', '00000003_VariantOnTranscript/Protein');
        $this->selectValue('00000003_effect_reported', 'Probably does not affect function');
        $this->selectValue('00000003_effect_concluded', 'Probably does not affect function');

        // Genomic fields.
        $this->selectValue('allele', 'Maternal (confirmed)');
        $this->assertValue('g.2828735T>A', 'VariantOnGenome/DNA');
        $this->enterValue('VariantOnGenome/Reference', '{PMID:Fokkema et al (2011):21520333}');
        $this->assertFalse($this->isElementPresent(WebDriverBy::name('effect_reported')));
        $this->assertFalse($this->isElementPresent(WebDriverBy::name('effect_concluded')));
        $this->driver->findElement(WebDriverBy::name('owned_by'));
        $this->driver->findElement(WebDriverBy::name('statusid'));
        $this->submitForm('Create variant entry');

        $this->assertStringStartsWith('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForURLContains('/src/variants/0000');
    }





    /**
     * @depends testCreateVariantWithinARSD
     */
    public function testResult ()
    {
        $this->assertEquals('Effect unknown', $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]//tr[th[contains(., "reported)")]]/td'))->getText());
        $this->assertEquals('Not classified', $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]//tr[th[contains(., "curator)")]]/td'))->getText());
    }
}
?>
