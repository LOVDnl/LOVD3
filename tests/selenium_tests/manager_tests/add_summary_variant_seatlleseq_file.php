<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2015-02-17
 * Modified    : 2020-05-15
 * For LOVD    : 3.0-24
 *
 * Copyright   : 2004-2020 Leiden University Medical Center; http://www.LUMC.nl/
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
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantSeatlleseqFileTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantSeatlleseqFile()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Genomic variant"));

        $this->assertContains("/src/variants/0000000167", $this->driver->getCurrentURL());
        $element = $this->driver->findElement(WebDriverBy::id("tab_submit"));
        $element->click();

        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/submit'));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        $this->chooseOkOnNextConfirmation();
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("variant_file"), ROOT_PATH . "../tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="hg_build"]/option[text()="hg19"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="dbSNP_column"]/option[text()="VariantOnGenome/Reference"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="autocreate"]/option[text()="Create genes and transcripts"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Upload SeattleSeq file']"));
        $this->clickNoTimeout($element);
        for ($second = 0; ; $second++) {
            if ($second >= 300) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::xpath("//input[@value='Continue »']"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }

        $this->assertContains("138 variants were imported, 1 variant could not be imported.", $this->driver->findElement(WebDriverBy::id("lovd__progress_message"))->getText());
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        
    }
}
