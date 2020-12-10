<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2020-11-30
 * For LOVD    : 3.0-26
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

class CreateSummaryDataUploadSeattleseqTest extends LOVDSeleniumWebdriverBaseTestCase
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
            '//table[@class="option"]//td[contains(., "I want to upload a SeattleSeq Annotation file")]'))->click();
    }





    /**
     * @depends testNavigateMenu
     */
    public function testUploadFile ()
    {
        $this->waitForURLEndsWith('/src/variants/upload?create&type=SeattleSeq');
        $this->enterValue('variant_file', ROOT_PATH . '../tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt');
        $this->selectValue('hg_build', 'hg19');
        $this->selectValue('dbSNP_column', 'VariantOnGenome/Reference');
        $this->selectValue('autocreate', 'Create genes and transcripts');
        $this->driver->findElement(WebDriverBy::name('owned_by'));
        $this->driver->findElement(WebDriverBy::name('statusid'));
        $this->submitForm('Upload SeattleSeq file');

        // Tests often time out here (2 minutes). Progress got stuck at
        //  different percentages; measure if this is just slow or if it really
        //  gets stuck for some reason.
        // For a whole minute, write out the progress every 5 seconds.
        for ($i = 0; $i < 60; $i += 5) {
            $sProgress = $this->driver->findElement(
                WebDriverBy::id('lovd__progress_value'))->getText();
            fwrite(STDERR, PHP_EOL . 'Progress output: ' . $sProgress);
            if ($sProgress == '100%') {
                fwrite(STDERR, PHP_EOL);
                break;
            }
            sleep(5);
        }

        $this->waitForElement(WebDriverBy::xpath('//input[contains(@value, "Continue")]'), 300);

        $this->assertEquals('138 variants were imported, 1 variant could not be imported.',
            $this->driver->findElement(WebDriverBy::id('lovd__progress_message'))->getText());
        $this->submitForm('Continue');

        $this->waitForURLContains('/src/submit/finish/upload/');
        $this->assertStringStartsWith('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }
}
?>
