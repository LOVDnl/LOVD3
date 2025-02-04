<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-15
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

class CreateSubmissionIndividualWithIVATest extends LOVDSeleniumWebdriverBaseTestCase
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
        $this->driver->get(ROOT_URL . '/src/diseases/IVA');
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Disease does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }

        // Check for the user ID. The user ID indicates its level; in Travis tests, our users are:
        // 1 - Admin.
        // 2 - Manager.
        // 3 - Curator.
        // 4 - Collaborator.
        // 5 - Owner.
        // 6 - Submitter (colleague).
        $sHref = $this->driver->findElement(WebDriverBy::xpath('//a[.="Your account"]'))->getAttribute('href');
        // HREF was relative, but WebDriver returns it as absolute.
        $aHref = explode('/', $sHref);
        $nUserID = (int) array_pop($aHref);
        $this->assertGreaterThan(0, $nUserID);

        // This is the only way PHPUnit allows us to share data between tests.
        return $nUserID;
    }





    /**
     * @depends testSetUp
     */
    public function testCreateIndividual ($nUserID)
    {
        $this->driver->get(ROOT_URL . '/src/submit');
        // This test does not demand you're Curator or up.
        if ($this->isElementPresent(WebDriverBy::xpath('//table[@class="option"]'))) {
            // Apparently, we are Curator or up.
            // We'll be submitting an individual.
            // NOTE: . takes the element's string value (ignoring other elements), so cleaner than text().
            $this->driver->findElement(WebDriverBy::xpath(
                '//table[@class="option"]//td[contains(., "Yes, I want to submit")]'))->click();
        }

        $this->waitForURLEndsWith('/src/individuals?create');
        $this->enterValue('Individual/Lab_ID', '1234IVA');
        $this->enterValue('Individual/Reference', '{PMID:Fokkema et al (2011):21520333}');
        $this->selectValue('active_diseases[]', 'IVA (isovaleric acidemia)');

        // Check for the owner and status fields, if you're curator and up.
        if ($nUserID <= 3) {
            $this->selectValue('owned_by', 'Test Owner (#00005)');
            $this->driver->findElement(WebDriverBy::name('statusid'));
        } else {
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('owned_by')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('statusid')));
        }
        $this->submitForm('Create individual information entry');

        $this->assertEquals('Successfully created the individual information entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));

        return $nUserID;
    }





    /**
     * @depends testCreateIndividual
     */
    public function testAddPhenotypeRecord ($nUserID)
    {
        $this->assertStringContainsString('/src/submit/individual/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to add phenotype information")]'))->click();

        $this->waitForURLContains('/src/phenotypes?create&target=0000');
        $this->enterValue('Phenotype/Additional', 'Additional information.');
        $this->selectValue('Phenotype/Inheritance', 'Familial');
        $this->enterValue('Phenotype/Age/Diagnosis', '30y');

        // Check for the owner and status fields, if you're curator and up.
        if ($nUserID <= 3) {
            $this->selectValue('owned_by', 'Test Owner (#00005)');
            $this->driver->findElement(WebDriverBy::name('statusid'));
        } else {
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('owned_by')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('statusid')));
        }
        $this->submitForm('Create phenotype information entry');

        $this->assertEquals('Successfully created the phenotype entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));

        return $nUserID;
    }





    /**
     * @depends testAddPhenotypeRecord
     */
    public function testAddScreening ($nUserID)
    {
        $this->assertStringContainsString('/src/submit/individual/0000', $this->driver->getCurrentURL());

        // This click often timeouts for no reason.
        $oLocator = WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to add a variant screening")]');
        for ($i = 0; $i < 5; $i ++) {
            if ($this->isElementPresent($oLocator)) {
                $this->driver->findElement($oLocator)->click();
                sleep(1);
            } else {
                break;
            }
        }

        $this->waitForURLContains('/src/screenings?create&target=0000');
        // selectValue() allows for multiple selection.
        $this->selectValue('Screening/Template[]', 'DNA');
        $this->selectValue('Screening/Template[]', 'RNA (cDNA)');
        // selectValue() allows for multiple selection.
        $this->selectValue('Screening/Technique[]', 'SEQ');
        $this->selectValue('Screening/Technique[]', 'RT-PCR');
        $this->selectValue('genes[]', 'IVD');
        $this->check('variants_found');

        // Check for the owner field, if you're curator and up.
        if ($nUserID <= 3) {
            $this->selectValue('owned_by', 'Test Owner (#00005)');
        } else {
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('owned_by')));
        }
        $this->submitForm('Create screening information entry');

        $this->assertEquals('Successfully created the screening entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));

        return $nUserID;
    }





    /**
     * @depends testAddScreening
     */
    public function testAddVariantWithinIVD ($nUserID)
    {
        $this->assertStringContainsString('/src/submit/screening/0000', $this->driver->getCurrentURL());

        // This click often timeouts for no reason.
        $oLocator = WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to add a variant to")]');
        for ($i = 0; $i < 5; $i ++) {
            if ($this->isElementPresent($oLocator)) {
                $this->driver->findElement($oLocator)->click();
                sleep(1);
            } else {
                break;
            }
        }

        $this->waitForURLContains('/src/variants?create&target=0000');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "A variant that is located within a gene")]'))->click();
        // We probably don't need to search for IVD, but we might as well.
        try {
            // Travis' Chrome keeps failing here with a StaleElementReferenceException without refreshes.
            $this->enterValue('search_id_', 'IVD' . WebDriverKeys::ENTER);
        } catch (StaleElementReferenceException $e) {}
        sleep(1);
        $this->driver->findElement(WebDriverBy::xpath('//tr[@id="IVD"]/td[1]'))->click();

        $this->waitForURLContains('/src/variants?create&reference=Transcript&geneid=IVD&target=0000');
        $this->enterValue('00000001_VariantOnTranscript/Exon', '1');
        $this->enterValue('00000001_VariantOnTranscript/DNA', 'c.123A>T');
        $this->driver->findElement(WebDriverBy::cssSelector('button.mapVariant'))->click();

        // Wait until RNA description field is filled after AJAX request, and check all values.
        $this->waitForValueContains('00000001_VariantOnTranscript/RNA', 'r.');
        $this->assertValue('r.(=)', '00000001_VariantOnTranscript/RNA');
        $this->assertValue('p.(=)', '00000001_VariantOnTranscript/Protein');
        $this->selectValue('00000001_effect_reported', 'Does not affect function');

        // Check for the effect_concluded, owner, and status fields, if you're curator and up.
        if ($nUserID <= 3) {
            $this->selectValue('00000001_effect_concluded', 'Does not affect function');
            $this->selectValue('owned_by', 'Test Owner (#00005)');
            $this->driver->findElement(WebDriverBy::name('statusid'));
        } else {
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('00000001_effect_concluded')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('owned_by')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('statusid')));
        }

        $this->selectValue('allele', 'Maternal (confirmed)');
        $this->assertValue('g.40698142A>T', 'VariantOnGenome/DNA');
        $this->enterValue('VariantOnGenome/Reference', '{PMID:Fokkema et al (2011):21520333}');
        $this->assertFalse($this->isElementPresent(WebDriverBy::name('effect_reported')));
        $this->assertFalse($this->isElementPresent(WebDriverBy::name('effect_concluded')));
        $this->submitForm('Create variant entry');

        $this->assertEquals('Successfully created the variant entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));

        return $nUserID;
    }





    /**
     * @depends testAddVariantWithinIVD
     */
    public function testAddVariantOnGenomicLevel ($nUserID)
    {
        $this->assertStringContainsString('/src/submit/screening/0000', $this->driver->getCurrentURL());

        // This click often timeouts for no reason.
        $oLocator = WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to add a variant to")]');
        for ($i = 0; $i < 5; $i ++) {
            if ($this->isElementPresent($oLocator)) {
                $this->driver->findElement($oLocator)->click();
                sleep(1);
            } else {
                break;
            }
        }

        $this->waitForURLContains('/src/variants?create&target=0000');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "A variant that was only described on genomic level")]'))->click();

        $this->waitForURLContains('/src/variants?create&reference=Genome&target=0000');
        $this->assertFalse($this->isElementPresent(WebDriverBy::xpath('//input[contains(@name, "VariantOnTranscript/")]')));
        $this->selectValue('allele', 'Paternal (confirmed)');
        $this->selectValue('chromosome', '15');
        $this->enterValue('VariantOnGenome/DNA', 'g.40702876G>T');
        $this->enterValue('VariantOnGenome/Reference', '{PMID:Fokkema et al (2011):21520333}');
        $this->selectValue('effect_reported', 'Effect unknown');

        // Check for the effect_concluded, owner, and status fields, if you're manager and up.
        if ($nUserID <= 2) {
            $this->selectValue('effect_concluded', 'Effect unknown');
            $this->selectValue('owned_by', 'Test Owner (#00005)');
            $this->driver->findElement(WebDriverBy::name('statusid'));
        } else {
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('effect_concluded')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('owned_by')));
            $this->assertFalse($this->isElementPresent(WebDriverBy::name('statusid')));
        }
        $this->submitForm('Create variant entry');

        $this->assertEquals('Successfully created the variant entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));
    }





    /**
     * @depends testAddVariantOnGenomicLevel
     */
    public function testFinishSubmission ()
    {
        $this->assertStringContainsString('/src/submit/screening/0000', $this->driver->getCurrentURL());

        // This click often timeouts for no reason.
        $oLocator = WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to finish this submission")]');
        for ($i = 0; $i < 5; $i ++) {
            if ($this->isElementPresent($oLocator)) {
                $this->driver->findElement($oLocator)->click();
                sleep(1);
            } else {
                break;
            }
        }

        $this->waitForURLContains('/src/submit/finish/individual/0000');
        $this->assertStringStartsWith('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForURLContains('/src/individuals/0000');
    }
}
?>
