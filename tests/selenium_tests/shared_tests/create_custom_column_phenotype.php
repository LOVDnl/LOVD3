<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-19
 * Modified    : 2020-05-19
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

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateCustomPhenotypeColumnTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/columns/Phenotype/Age/Diagnosis');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Column was already created.');
        }

        // Requires having a Setup tab.
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/columns?create');
        $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="option"]//td[contains(., "Information on the phenotype")]'))->click();

        $this->assertContains('/src/columns?create', $this->driver->getCurrentURL());
        $this->enterValue('colid', 'Age/Diagnosis');
        $this->enterValue('head_column', 'Age of diagnosis');
        $this->enterValue('description_legend_short', 'The age at which the individual\'s diagnosis was confirmed, if known. 04y08m = 4 years and 8 months.');
        $this->enterValue('description_legend_full', 'The age at which the individual\'s diagnosis was confirmed, if known.\r\n<UL style=\"margin-top:0px;\">\r\n  <LI>35y = 35 years</LI>\r\n  <LI>04y08m = 4 years and 8 months</LI>\r\n  <LI>18y? = around 18 years</LI>\r\n  <LI>&gt;54y = older than 54</LI>\r\n  <LI>? = unknown</LI>\r\n</UL>');

        // Open data type wizard to continue.
        // Store current window handler.
        $aWindowHandlers = $this->driver->getWindowHandles();
        $this->assertEquals(1, count($aWindowHandlers));
        $sMainWindowHandler = current($aWindowHandlers);

        // Open new window, and store new window handler.
        $this->driver->findElement(WebDriverBy::xpath('//button[text()="Start data type wizard"]'))->click();
        $sPopupWindowHandler = current(array_diff(
            $this->driver->getWindowHandles(),
            $aWindowHandlers
        ));

        // Data wizard.
        $this->driver->switchTo()->window($sPopupWindowHandler);
        $this->assertContains('/src/columns?data_type_wizard&workID=', $this->driver->getCurrentURL());
        $this->selectValue('form_type', 'text');
        $this->submitForm('Next');

        $this->enterValue('name', 'Age of diagnosis');
        $this->enterValue('help_text', 'The age at which the individual\'s diagnosis was confirmed, if known. Numbers lower than 10 should be prefixed by a zero and the field should always begin with years, to facilitate sorting on this column.');
        $this->enterValue('description_form', 'Type 35y for 35 years, 04y08m for 4 years and 8 months, 18y? for around 18 years, >54y for older than 54, ? for unknown.');
        $this->enterValue('size', '10');
        $this->enterValue('maxlength', '12');
        $this->enterValue('preg_pattern', '/^([<>]?\d{2,3}y(\d{2}m(\d{2}d(\d{2}h)?)?)?|\d{2,3}y(\d{2}m(\d{2}d(\d{2}h)?)?)?-\d{2,3}y(\d{2}m(\d{2}d(\d{2}h)?)?)?)?\??$/');
        $this->submitForm('Finish');

        // Window closes, let's switch back.
        $this->driver->switchTo()->window($sMainWindowHandler);
        $this->assertContains('/src/columns?create', $this->driver->getCurrentURL());
        $this->assertValue('VARCHAR(12)', 'mysql_type');
        $this->assertValue('Age of diagnosis|The age at which the individual\'s diagnosis was confirmed, if known. Numbers lower than 10 should be prefixed by a zero and the field should always begin with years, to facilitate sorting on this column.|text|10', 'form_type');
        $this->unCheck('standard');
        $this->enterValue('width', '100');
        $this->unCheck('mandatory');
        $this->check('public_view');
        $this->check('public_add');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Create new custom phenotype data column');
        $this->assertEquals('Successfully created the new "Phenotype/Age/Diagnosis" column!',
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/columns/Phenotype/Age/Diagnosis'));
    }
}
?>
