<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-11-10
 * Modified    : 2024-05-24
 * For LOVD    : 3.0-30
 *
 * Copyright   : 2004-2024 Leiden University Medical Center; http://www.LUMC.nl/
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

class MultiValueSearchTest extends LOVDSeleniumWebdriverBaseTestCase
{
    protected function setUp (): void
    {
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
        $this->driver->get(ROOT_URL . '/src/variants/IVD/unique');

        // First, determine which field is the protein field.
        $aColumns = $this->driver->findElements(
            WebDriverBy::xpath('//table[@class="data"]/thead/tr/th'));
        $iProteinColumn = false;
        foreach ($aColumns as $nKey => $oColumn) {
            if (trim($oColumn->getText()) == 'Protein') {
                $iProteinColumn = ($nKey+1); // xpath starts counting at 1.
            }
        }
        $this->assertNotFalse($iProteinColumn);

        // Start the process.
        $this->driver->findElement(WebDriverBy::id(
            'viewlistOptionsButton_CustomVL_VOTunique_VOG_IVD'))->click();
        $this->driver->findElement(WebDriverBy::linkText(
            'Enable or disable filtering on multivalued rows'))->click();

        // Include explicit wait for overlay divs. Going directly to clicking sometimes
        //  results in a StaleElementReferenceException.
        $this->waitUntil(function ($driver) use ($iProteinColumn) {
            return (count($driver->findElements(WebDriverBy::xpath(
                '//div[@class="vl_overlay"]'))) >= $iProteinColumn);
        });
        $this->driver->findElement(WebDriverBy::xpath(
            '//div[@class="vl_overlay"][' . $iProteinColumn . ']'))->click();

        // Wait until the viewlist contains 1 row (the number of
        //  variants with >1 protein description).
        $this->waitUntil(function ($driver) {
            return (count($driver->findElements(
                WebDriverBy::xpath('//table[@class="data"]/tbody/tr'))) == 1);
        });
    }
}
?>
