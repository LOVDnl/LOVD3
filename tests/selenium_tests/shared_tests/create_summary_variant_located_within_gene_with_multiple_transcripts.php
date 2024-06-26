<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
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
        // We probably don't need to search for IVD, but we might as well.
        try {
            // Travis' Chrome keeps failing here with a StaleElementReferenceException without refreshes.
            $this->enterValue('search_id_', 'IVD' . WebDriverKeys::ENTER);
        } catch (StaleElementReferenceException $e) {}
        sleep(1);
        $this->driver->findElement(WebDriverBy::xpath('//tr[@id="IVD"]/td[1]'))->click();
    }





    /**
     * @depends testNavigateMenu
     */
    public function testCreateVariantWithinIVD ()
    {
        $this->waitForURLContains('/src/variants?create&reference=Transcript&geneid=IVD');
        // Test if ignoring a transcript works.
        $this->check('ignore_00000001');
        $this->assertFalse($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000001_VariantOnTranscript/DNA"]'))->isEnabled());
        $this->unCheck('ignore_00000001');
        $this->assertTrue($this->driver->findElement(WebDriverBy::xpath('//input[@name="00000001_VariantOnTranscript/DNA"]'))->isEnabled());

        // Fill in NM_002225.3 transcript (ID #00000001).
        $this->enterValue('00000001_VariantOnTranscript/Exon', '10');
        $this->enterValue('00000001_VariantOnTranscript/DNA', 'c.1000A>T');
        // We can't use the normal button selector, because there are multiple transcripts and not all buttons are visible.
        // $this->driver->findElement(WebDriverBy::cssSelector('button.mapVariant'))->click();
        $this->driver->findElement(WebDriverBy::xpath('//input[@name="00000001_VariantOnTranscript/DNA"]/../button'))->click();

        // Wait until RNA description field is filled after AJAX request, and check all values.
        $this->waitForValueContains('00000001_VariantOnTranscript/RNA', 'r.');
        $this->assertValue('r.(?)', '00000001_VariantOnTranscript/RNA');
        $this->assertValue('p.(Thr334Ser)', '00000001_VariantOnTranscript/Protein');
        $this->selectValue('00000001_effect_reported', 'Effect unknown');
        $this->selectValue('00000001_effect_concluded', 'Not classified');

        // Fill in NM_001159508.1 transcript, but first get the ID.
        $eTR = $this->driver->findElement(WebDriverBy::xpath('//b[@class="transcript"][contains(.,"NM_001159508.1")]'));
        $nTranscriptID = $eTR->getAttribute('transcriptid');
        $this->enterValue($nTranscriptID . '_VariantOnTranscript/Exon', '9');
        $this->assertValue('c.910A>T', $nTranscriptID . '_VariantOnTranscript/DNA');
        $this->assertValue('r.(?)', $nTranscriptID . '_VariantOnTranscript/RNA');
        $this->assertValue('p.(Thr304Ser)', $nTranscriptID . '_VariantOnTranscript/Protein');
        $this->selectValue($nTranscriptID . '_effect_reported', 'Effect unknown');
        $this->selectValue($nTranscriptID . '_effect_concluded', 'Not classified');

        // Genomic fields.
        $this->selectValue('allele', 'Maternal (confirmed)');
        $this->assertValue('g.40708307A>T', 'VariantOnGenome/DNA');
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
     * @depends testCreateVariantWithinIVD
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
