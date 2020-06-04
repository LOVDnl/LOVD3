<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-06-05
 * Modified    : 2020-05-28
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

class EnableCustomColumnForIndividualsTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/columns/Individual/Gender');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Column does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/columns/Individual/Gender');
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Columns'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Enable column'))->click();

        $this->assertStringEndsWith('/src/columns/Individual/Gender?add', $this->driver->getCurrentURL());
        $this->enterValue('password', 'test1234');
        $this->submitForm('Add/enable custom data column');
        $this->assertEquals('Successfully added column "Gender"!',
            $this->driver->findElement(WebDriverBy::id('lovd__progress_message_done'))->getText());

        // Wait for page redirect.
        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/columns/Individual'));
    }
}
?>