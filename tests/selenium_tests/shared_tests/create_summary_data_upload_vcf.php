<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2021-04-14
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
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

class CreateSummaryDataUploadVCFTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/submit');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
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
            '//table[@class="option"]//td[contains(., "I want to upload a file")]'))->click();

        $this->waitForURLEndsWith('/src/variants/upload?create');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to upload a Variant Call Format (VCF) file")]'))->click();
    }





    /**
     * @depends testNavigateMenu
     */
    public function testUploadFile ()
    {
        $this->waitForURLEndsWith('/src/variants/upload?create&type=VCF');
        $this->enterValue('variant_file', ROOT_PATH . '../tests/test_data_files/ShortVCFfilev1.vcf');
        $this->selectValue('hg_build', 'hg19');
        $this->selectValue('dbSNP_column', 'VariantOnGenome/Reference');
        $this->selectValue('genotype_field', 'Use Phred-scaled genotype likelihoods (PL)');
        $this->check('allow_mapping');
        $this->check('allow_create_genes');
        $this->driver->findElement(WebDriverBy::name('owned_by'));
        $this->driver->findElement(WebDriverBy::name('statusid'));
        $this->submitForm('Upload VCF file');
        $this->waitForElement(WebDriverBy::xpath('//input[contains(@value, "Continue")]'), 5);

        $this->assertEquals('25 variants were imported, 1 variant could not be imported.',
            $this->driver->findElement(WebDriverBy::id('lovd__progress_message'))->getText());
        $this->submitForm('Continue');

        $this->waitForURLContains('/src/submit/finish/upload/');
        $this->assertStringStartsWith('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());

        // Now map the variants. Note that tabs are replaced by spaces,
        //  because we work with the browser's interpretation of the text.
        $bRepeated = false;
        do {
            $this->driver->get(ROOT_URL . '/src/ajax/map_variants.php');
            // We get failures sometimes in the download verification test,
            //  because the mapping apparently did not complete.
            // For now, log the output that we get. Maybe there's a pattern.
            $sBody = rtrim($this->driver->findElement(WebDriverBy::tagName('body'))->getText());
            fwrite(STDERR, PHP_EOL . 'Mapping output: ' . $sBody . PHP_EOL);

            // We sometimes get failures, when LOVD says we're done mapping,
            //  but we're actually not.
            if (substr($sBody, 0, 5) == '0 99 ' && !$bRepeated) {
                // Just one more time, please!
                $sBody = '';
                $bRepeated = true;
            } elseif (substr($sBody, 0, 5) != '0 99 ') {
                // Reset timer in case we are again mapping.
                $bRepeated = false;
            }
        } while (substr($sBody, 0, 5) != '0 99 ');
        // Travis sometimes reports a "There are no variants to map in the
        //  database" instead of the expected "Successfully mapped 25 variants".
        $this->assertContains(
            $this->driver->findElement(WebDriverBy::tagName('body'))->getText(),
            array(
              '0 99 Successfully mapped ',
              '0 99 There are no variants to map in the database',
            ));
    }
}
?>
