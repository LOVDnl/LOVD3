<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-11-10
 * Modified    : 2017-11-10
 * For LOVD    : 3.0-21
 *
 * Copyright   : 2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : M. Kroon <m.kroon@lumc.nl>
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

    public function testMultiValueSearch()
    {
        // Test Multivalued search on the protein column of the unique variant
        // viewlist of gene NOC2L.

        $this->driver->get(ROOT_URL . "/src/variants/NOC2L/unique");

        // Click on mutli value search menu option.
        $gearOptionsLink = $this->driver->findElement(
            WebDriverBy::id('viewlistOptionsButton_CustomVL_VOTunique_VOG_NOC2L'));
        $gearOptionsLink->click();
        $MVSMenuItem = $this->driver->findElement(
            WebDriverBy::partialLinkText('Enable or disable filtering on multivalued'));
        $MVSMenuItem->click();

        // Click on overlay of protein column.
        $nProteinColIndex = 7;

        // Include explicit wait for overlay divs. Going directly to clicking sometimes
        // results in a StaleElementReferenceException.
        $this->waitUntil(function ($driver) use ($nProteinColIndex) {
            $aOverlays = $driver->findElements(WebDriverBy::xpath('//div[@class="vl_overlay"]'));
            return count($aOverlays) >= $nProteinColIndex;
        });
        $columnOverlay = $this->driver->findElement(
            WebDriverBy::xpath('//div[@class="vl_overlay"][' . $nProteinColIndex . ']'));
        $columnOverlay->click();

        // Wait until the viewlist contains 2 rows (2 is the number of
        // variants with >1 protein description).
        $this->waitUntil(function ($driver) {
            $aVLRows = $driver->findElements(
                WebDriverBy::xpath('//table[@id="viewlistTable_CustomVL_VOTunique_VOG_NOC2L"]/tbody/tr'));
            return count($aVLRows) == 2;
        });
    }
}


