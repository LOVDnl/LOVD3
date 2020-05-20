<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-20
 * Modified    : 2020-05-20
 * For LOVD    : 3.0-24
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

class AddMoreDataToExistingSubmissionTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/individuals');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//tr[td[text()="IVD"] and td[text()="IVA"]]'))) {
            $this->markTestSkipped('Individual with IVD variant and IVA disease does not exist.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testFindIndividual ()
    {
        $this->driver->get(ROOT_URL . '/src/individuals');
        $this->driver->findElement(WebDriverBy::xpath('//tr[td[text()="IVD"] and td[text()="IVA"]]'))->click();
    }





    /**
     * @depends testFindIndividual
     */
    public function testAddPhenotypeRecord ()
    {
        $this->assertContains('/src/individuals/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Individuals'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Add phenotype information to individual'))->click();

        $this->assertContains('/src/phenotypes?create&target=0000', $this->driver->getCurrentURL());
        $this->enterValue('Phenotype/Additional', 'More additional information.');
        $this->selectValue('Phenotype/Inheritance', 'Familial');
        $this->enterValue('Phenotype/Age/Diagnosis', '30y');
        $this->submitForm('Create phenotype information entry');

        $this->assertContains('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/phenotypes/0000'));
        $this->driver->findElement(WebDriverBy::xpath('//a[contains(@href, "individuals/0000")]'))->click();
    }





    /**
     * @depends testAddPhenotypeRecord
     */
    public function testAddScreening ()
    {
        $this->assertContains('/src/individuals/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Individuals'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Add screening to individual'))->click();

        $this->assertContains('/src/screenings?create&target=0000', $this->driver->getCurrentURL());
        $this->selectValue('Screening/Template[]', 'Protein');
        $this->selectValue('Screening/Technique[]', 'Western');
        $this->selectValue('genes[]', 'IVD');
        $this->check('variants_found');
        $this->submitForm('Create screening information entry');

        $this->assertEquals('Successfully created the screening entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));
    }





    /**
     * @depends testAddScreening
     */
    public function testConfirmVariant ()
    {
        $this->assertContains('/src/submit/screening/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to add a variant to")]'))->click();

        $this->assertContains('/src/variants?create&target=0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "Yes, I want to confirm variants found")]'))->click();

        $this->assertRegExp('/\/src\/screenings\/[0-9]+\?confirmVariants$/', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath('//td[text()="?/?"]'))->click();
        $this->submitForm('Save variant list');

        $this->assertEquals('Successfully confirmed the variant entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
        $this->waitForElement(WebDriverBy::xpath('//table[@class="option"]'));
    }





    /**
     * @depends testConfirmVariant
     */
    public function testFinishSubmission ()
    {
        $this->assertContains('/src/submit/screening/0000', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "I want to finish this submission")]'))->click();

        $this->assertContains('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }
}
?>
