<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-04
 * Modified    : 2021-01-06
 * For LOVD    : 3.0-26
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

class CreateGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
        // Test if we have what we need for this test. If not, skip this test.
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/genes/IVD');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
        if (!preg_match('/No such ID!/', $sBody)) {
            $this->markTestSkipped('Gene was already created.');
        }
        if (!$this->isElementPresent(WebDriverBy::id('tab_setup'))) {
            $this->markTestSkipped('User was not authorized.');
        }
    }





    public function test ()
    {
        $this->driver->get(ROOT_URL . '/src/genes?create');
        $this->waitForElement(WebDriverBy::name('hgnc_id'), 5);
        $this->enterValue('hgnc_id', 'IVD');
        $this->submitForm('Continue');
        $this->waitForElement(WebDriverBy::xpath('//select[@name="active_transcripts[]"]'));

        $this->selectValue('active_transcripts[]', 'NM_002225.3');
        $this->check('show_hgmd');
        $this->check('show_genecards');
        $this->check('show_genetests');
        $this->check('show_orphanet');
        $this->submitForm('Create gene information entry');
        $this->assertEquals('Successfully created the gene information entry!',
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
?>
