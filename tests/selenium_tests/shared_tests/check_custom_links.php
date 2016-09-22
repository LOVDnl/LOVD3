<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-22
 * Modified    : 2016-09-22
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
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

class CheckCustomLinks extends LOVDSeleniumWebdriverBaseTestCase
{

    public function testCheckCustomLinks ()
    {
        // This test checks some basic functionality of the custom links. It
        // doesn't require authorization, just checks some VLs if the links are
        // rendered correctly.

        // Load the common variant overview.
        $this->driver->get(ROOT_URL . '/src/variants');

        // Find a custom link, and move the mouse over it.
        $oCustomLink = $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]/tbody/tr/td/span[text()="dbSNP"]'));
        $this->driver->getMouse()->mouseMove($oCustomLink->getCoordinates());

        // Now find the tooltip that should have shown.
        $sToolTipLinkText = $this->driver->findElement(WebDriverBy::xpath('//div[@id="tooltip"]/a'))->getText();
        $this->assertTrue((strpos($sToolTipLinkText, 'http') === 0 && strpos($sToolTipLinkText, 'SNP')));

        // This test data does not have many links, try and find a PubMed link.
        // Load the in_gene view.
        $this->driver->get(ROOT_URL . '/src/variants/in_gene');

        // Find a custom link, and move the mouse over it.
        $oCustomLink = $this->driver->findElement(WebDriverBy::xpath('//table[@class="data"]/tbody/tr/td/span[text()="Fokkema et al (2011)"]'));
        $this->driver->getMouse()->mouseMove($oCustomLink->getCoordinates());

        // Now find the tooltip that should have shown.
        $sToolTipLinkText = $this->driver->findElement(WebDriverBy::xpath('//div[@id="tooltip"]/a'))->getText();
        $this->assertTrue((strpos($sToolTipLinkText, 'http') === 0 && strpos($sToolTipLinkText, 'pubmed')));
    }
}
