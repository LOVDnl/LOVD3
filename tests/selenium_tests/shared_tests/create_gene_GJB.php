<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : ?
 * Modified    : 2017-02-07
 * For LOVD    : 3.0-19
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

class CreateGeneGJBTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateGeneGJB()
    {
        // Open genes page.
        $this->driver->get(ROOT_URL . '/src/genes');

        // Mouse hover over genes tab, to make 'create a new gene entry' link visible.
        $tabElement = $this->driver->findElement(WebDriverBy::id("tab_genes"));
        $this->driver->getMouse()->mouseMove($tabElement->getCoordinates());

        $element = $this->driver->findElement(WebDriverBy::linkText("Create a new gene entry"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes[\s\S]create$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("hgnc_id"), "GJB1");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
//        $this->addSelection(WebDriverBy::name("active_transcripts[]"), "value=NM_001097642.2");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_transcripts[]"]/option[@value="NM_001097642.2"]'));
        $option->click();
        $this->check(WebDriverBy::name("show_hgmd"));
        $this->check(WebDriverBy::name("show_genecards"));
        $this->check(WebDriverBy::name("show_genetests"));
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create gene information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the gene information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        $this->waitUntil(WebDriverExpectedCondition::titleContains("GJB1 gene homepage"));
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/', $this->driver->getCurrentURL()));
    }
}
