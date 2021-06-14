<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-19
 * Modified    : 2020-06-05
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

class EnableCustomColumnForIVATest extends LOVDSeleniumWebdriverBaseTestCase
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
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Column does not exist yet.');
        }
        if (preg_match('/Column activated for diseases IVA/', $sBody)) {
            $this->markTestSkipped('Column was already activated.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_configuration'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/columns/Phenotype/Age/Diagnosis');
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Columns'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Enable column'))->click();

        $this->assertStringEndsWith('/src/columns/Phenotype/Age/Diagnosis?add', $this->driver->getCurrentURL());
        $this->selectValue('target[]', 'IVA (isovaleric acidemia)');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Add/enable custom data column');
        $this->assertEquals('Successfully added column "Age of diagnosis"!',
            $this->driver->findElement(WebDriverBy::id('lovd__progress_message_done'))->getText());

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/columns/Phenotype'));
    }
}
?>
