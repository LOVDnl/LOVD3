<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
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

class CreateDiseaseIVATest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        // IVA is disease ID 1.
        $this->driver->get(ROOT_URL . '/src/diseases/00001');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Disease was already created.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_configuration'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . "/src/diseases?create");
        $this->enterValue('symbol', 'IVA');
        $this->enterValue('name', 'isovaleric acidemia');
        $this->enterValue('id_omim', '243500');
        $this->selectValue('genes[]', 'IVD (isovaleryl-CoA dehydrogenase)');
        $this->submitForm('Create disease information entry');
        $this->assertEquals('Successfully created the disease information entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }
}
?>
