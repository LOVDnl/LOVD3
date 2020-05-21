<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2020-05-21
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

class DeleteGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp ()
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene does not exist yet.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $this->driver->findElement(WebDriverBy::id('viewentryOptionsButton_Genes'))->click();
        $this->driver->findElement(WebDriverBy::linkText('Delete gene entry'))->click();

        $this->assertContains('/src/genes/IVD?delete', $this->driver->getCurrentURL());
        $this->enterValue('password', 'test1234');
        $this->submitForm('Delete gene information entry');

        $this->assertRegExp('/^You are about to delete [0-9]+ transcript\(s\) and related information on [0-9]+ variant\(s\) on those transcripts. ' .
            'Please fill in your password one more time to confirm the removal of gene IVD\./',
            $this->driver->findElement(WebDriverBy::xpath('//table[@class="info"][2]'))->getText());
        $this->enterValue('password', 'test1234');
        $this->submitForm('Delete gene information entry');

        $this->assertEquals('Successfully deleted the gene information entry!',
            $this->driver->findElement(WebDriverBy::cssSelector('table[class=info]'))->getText());
    }
}
?>
