<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-05-27
 * Modified    : 2020-10-08
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

class DeleteDiseaseIVATest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/diseases/IVA');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Disease does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::xpath('//a[contains(@href, "users/0000")]/b[text()="Your account"]'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/diseases/IVA');
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Diseases'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Delete disease entry'))->click();

        $this->waitForURLRegExp('/\/src\/diseases\/[0-9]+\?delete$/');
        $this->enterValue('password', 'test1234');
        $this->submitForm('Delete disease information entry');

        $this->assertRegExp('/^You are about to delete [0-9]+ phenotype\(s\). ' .
            'Please fill in your password one more time to confirm the removal of disease [0-9]+\./',
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"][2]'))->getText());
        $this->enterValue('password', 'test1234');
        $this->submitForm('Delete disease information entry');

        $this->assertEquals('Successfully deleted the disease information entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }
}
?>
