<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016
 * Modified    : 2016-07-13
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2016 Leiden University Medical Center; http://www.LUMC.nl/
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
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddVCFFileToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddVCFFileToIVAIndividual()
    {
        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=VCF&target=0000000003$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("variant_file"), ROOT_PATH . "../tests/test_data_files/ShortVCFfilev1.vcf");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="hg_build"]/option[text()="hg19"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="dbSNP_column"]/option[text()="VariantOnGenome/Reference"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genotype_field"]/option[text()="Use Phred-scaled genotype likelihoods (PL)"]'));
        $option->click();
        $this->check(WebDriverBy::name("allow_mapping"));
        $this->check(WebDriverBy::name("allow_create_genes"));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Upload VCF file']"));
        $element->click();
        $this->assertEquals("25 variants were imported, 1 variant could not be imported.", $this->driver->findElement(WebDriverBy::id("lovd__progress_message"))->getText());
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        for ($second = 0; ; $second++) {
            if ($second >= 600) $this->fail("timeout");
            $this->driver->get(ROOT_URL . "/src/ajax/map_variants.php");

            if (strcmp("0 99 There are no variants to map in the database", $this->driver->findElement(WebDriverBy::tagName("body"))->getText())) {
                break;
            }
            $this->assertNotContains("of 25 variants", $this->driver->findElement(WebDriverBy::tagName("body"))->getText());
            sleep(1);
        }

        // Test whether a variant was parsed correctly via mutalyzer.
        $this->driver->get(ROOT_URL . '/src/genes/ARSD');
        $element = $this->driver->findElement(WebDriverBy::xpath('//tr[@class="data"]/td[text()="X"]'));
        $element->click();
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//td[text()="p.(Gln318His)"]')));
    }
}
