<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-06-17
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

use \Facebook\WebDriver\WebDriverBy;

class CurateSubmissionStepByStepTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp ()
    {
        // A normal setUp() runs for every test in this file. We only need this once,
        //  so we disguise this setUp() as a test that we depend on just once.
        $this->driver->get(ROOT_URL . '/src/configuration');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/To access this area, you need at least/', $sBody)) {
            $this->markTestSkipped('User was not authorized.');
        }
        $this->assertStringContainsString('IVD configuration', $sBody);
    }





    /**
     * @depends testSetUp
     */
    public function testFindSubmission ()
    {
        $this->driver->get(ROOT_URL . '/src/configuration');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="setup"]//a[text()="Pending"]'))->click();

        $this->waitForURLEndsWith('/src/view/IVD?search_var_status=%3D%22Pending%22');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td[text()="Pending"][2]'))->click();

        $this->waitForURLContains('/src/variants/0000');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td[span[text()="(Pending)"]]/a'))->click();
    }





    /**
     * @depends testFindSubmission
     */
    public function testCurateIndividual ()
    {
        $this->waitForURLContains('/src/individuals/0000');
        $sSubmissionURL = $this->driver->getCurrentURL();

        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Pending"]'));
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Individuals'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Publish (curate) individual entry'))->click();

        $this->waitForURLEquals($sSubmissionURL);
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Public"]'));
        return $sSubmissionURL;
    }





    /**
     * @depends testCurateIndividual
     */
    public function testCuratePhenotypes ($sSubmissionURL)
    {
        $sLocator = '//div[contains(@id, "viewlistDiv_Phenotypes_for_I_VE_0000")]//td[text()="Pending"]';
        while ($this->isElementPresent(WebDriverBy::xpath($sLocator))) {
            $this->driver->findElement(WebDriverBy::xpath($sLocator))->click();

            $this->waitForURLContains('/src/phenotypes/0000');
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Pending"]'));
            $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Phenotypes'))->click();
            $this->driver->findElement(WebDriverBy::linkText('Publish (curate) phenotype entry'))->click();

            $this->waitForURLContains('/src/phenotypes/0000');
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Public"]'));

            $this->driver->get($sSubmissionURL);
        }
        return $sSubmissionURL;
    }





    /**
     * @depends testCuratePhenotypes
     */
    public function testCurateVariants ($sSubmissionURL)
    {
        $sLocator = '//div[@id="viewlistDiv_CustomVL_VOT_for_I_VE"]//tr[td[text()="IVD"]]/td[text()="Pending"]';
        while ($this->isElementPresent(WebDriverBy::xpath($sLocator))) {
            $this->driver->findElement(WebDriverBy::xpath($sLocator))->click();

            $this->waitForURLContains('/src/variants/0000');
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td/span[text()="(Public)"]'));
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Pending"]'));
            $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Variants'))->click();
            $this->driver->findElement(WebDriverBy::linkText('Publish (curate) variant entry'))->click();

            $this->waitForURLContains('/src/variants/0000');
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"][1]//td/span[text()="Public"]'));

            $this->driver->get($sSubmissionURL);
        }
    }
}
?>
