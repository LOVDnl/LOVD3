<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-19
 * Modified    : 2020-07-23
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

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class ManageTranscriptsForVariantTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testSetUp()
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
        $this->driver->get(ROOT_URL . '/src/variants/chr15');
        if (!$this->isElementPresent(WebDriverBy::xpath('//table[@class="data"]//tr[td and not(td[contains(text(), "IVD_")])]'))) {
            $this->markTestSkipped('Candidate variant does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    /**
     * @depends testSetUp
     */
    public function testFindVariant()
    {
        $this->driver->get(ROOT_URL . '/src/variants/chr15');
        $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]//td[.="g.40702876G>T"]'))->click();

        $this->assertContains('/src/variants/0000', $this->driver->getCurrentURL());
        $this->assertEquals('No variants on transcripts found!',
            $this->driver->findElement(WebDriverBy::id('viewlistDiv_VOT_for_VOG_VE'))->getText());
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Variants'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Manage transcripts for this variant'))->click();
    }





    /**
     * @depends testFindVariant
     */
    public function testAddTranscript()
    {
        $this->assertRegExp('/\/src\/variants\/[0-9]+\?map$/', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath('//td[contains(text(), "NM_002225.3")]'))->click();
        $this->enterValue('password', 'test1234');
        $this->submitForm('Save transcript list');

        $this->assertEquals('Successfully updated the transcript list!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }





    /**
     * @depends testAddTranscript
     */
    public function testAddVOTAnnotation()
    {
        $this->waitUntil(WebDriverExpectedCondition::urlMatches('/\/src\/variants\/[0-9]+\?edit\#[0-9]+$/'));
        $this->driver->findElement(WebDriverBy::cssSelector('button.proteinChange'))->click();
        $this->waitForValueContains(WebDriverBy::xpath('//input[contains(@name, "_VariantOnTranscript/RNA")]'), 'r.');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Edit variant entry');

        $this->assertStringStartsWith('Successfully processed your submission',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }





    /**
     * @depends testAddVOTAnnotation
     */
    public function testVerifyAddedTranscript()
    {
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/variants/0000'));
        $this->driver->findElement(WebDriverBy::xpath('//td[text()="NM_002225.3"]'));
        $this->driver->findElement(WebDriverBy::xpath('//td[contains(text(), "p.(")]'));
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Variants'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Manage transcripts for this variant'))->click();
    }





    /**
     * @depends testVerifyAddedTranscript
     */
    public function testRemoveTranscript()
    {
        $this->assertRegExp('/\/src\/variants\/[0-9]+\?map$/', $this->driver->getCurrentURL());
        $this->driver->findElement(WebDriverBy::xpath('//tr[td[contains(text(), "NM_002225.3")]]/td/a'))->click();
        $this->assertStringStartsWith('You are about to remove the variant description of transcript NM_002225.3 from this variant.',
            $this->getConfirmation());
        $this->chooseOkOnNextConfirmation();
        $this->enterValue('password', 'test1234');
        $this->submitForm('Save transcript list');

        $this->assertEquals('Successfully updated the transcript list!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }





    /**
     * @depends testRemoveTranscript
     */
    public function testVerifyRemovedTranscript()
    {
        $this->waitUntil(WebDriverExpectedCondition::urlMatches('/\/src\/variants\/[0-9]+$/'));
        $this->assertEquals('No variants on transcripts found!',
            $this->driver->findElement(WebDriverBy::id('viewlistDiv_VOT_for_VOG_VE'))->getText());
    }
}
?>
