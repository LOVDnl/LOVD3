<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-22
 * Modified    : 2023-01-27
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
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
use \Facebook\WebDriver\WebDriverKeys;

class CheckCustomLinks extends LOVDSeleniumWebdriverBaseTestCase
{
    public function setUp ()
    {
        parent::setUp();
        $this->driver->get(ROOT_URL . '/src/variants');
        $sBody = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
        if (preg_match('/LOVD was not installed yet/', $sBody)) {
            $this->markTestSkipped('LOVD was not installed yet.');
        }
    }





    public function test ()
    {
        // This test checks some basic functionality of the custom links. It
        // doesn't require authorization, just checks some VLs if the links are
        // rendered correctly.

        $this->driver->get(ROOT_URL . '/src/variants');

        // Find a custom link, and move the mouse over it.
        // We take the last custom link, because sometimes the first one(s)
        //  are covered by a tab's dropdown menu that's somehow open.
        $oCustomLink = $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]/tbody/tr[td/span[text()="dbSNP"]][last()]/td/span[text()="dbSNP"]'));
        $this->driver->scrollToElement($oCustomLink);
        $this->driver->getMouse()->mouseMove($oCustomLink->getCoordinates());

        // Now find the tooltip that should have shown.
        $sToolTipLinkText = $this->driver->findElement(WebDriverBy::xpath('//div[@id="tooltip"]/a'))->getText();
        $this->assertStringStartsWith('https://www.ncbi.nlm.nih.gov/snp/', $sToolTipLinkText);

        // This test data does not have many links, try and find a PubMed link.
        $this->driver->get(ROOT_URL . '/src/variants/in_gene');
        $this->enterValue('search_VariantOnGenome/Reference', 'Fokkema' . WebDriverKeys::ENTER);
        $this->waitForElement(WebDriverBy::xpath('//table[@class="data"]/tbody/tr/td/span[text()="Fokkema et al (2011)"]'));

        // Find a custom link, and move the mouse over it.
        $oCustomLink = $this->driver->findElement(WebDriverBy::xpath(
            '//table[@class="data"]/tbody/tr/td/span[text()="Fokkema et al (2011)"]'));
        $this->driver->getMouse()->mouseMove($oCustomLink->getCoordinates());

        // Now find the tooltip that should have shown.
        $sToolTipLinkText = $this->driver->findElement(WebDriverBy::xpath('//div[@id="tooltip"]/a'))->getText();
        $this->assertStringStartsWith('https://pubmed.ncbi.nlm.nih.gov/', $sToolTipLinkText);
    }
}
?>
