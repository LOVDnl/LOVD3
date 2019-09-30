<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016
 * Modified    : 2016-07-18
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

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantLocatedWithinGene()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_submit"));
        $element->click();

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        $this->chooseOkOnNextConfirmation();
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::cssSelector("#ARSD > td.ordered"));
        $element->click();

        $this->assertContains("src/variants?create&reference=Transcript&geneid=ARSD", $this->driver->getCurrentURL());
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::name("ignore_00000002"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $this->uncheck(WebDriverBy::name("ignore_00000003"));
        $this->enterValue(WebDriverBy::name("00000002_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000002_VariantOnTranscript/DNA"), "c.62T>A");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();

        // Wait until the first two RNA-change input fields contain data.
        $firstRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[1]';
        $secondRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[2]';
        $this->waitUntil(function ($driver) use ($firstRNAInputSelector, $secondRNAInputSelector) {
            $firstRNAInput = $driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
            $firstRNAValue = $firstRNAInput->getAttribute('value');
            $secondRNAInput = $driver->findElement(WebDriverBy::xpath($secondRNAInputSelector));
            $secondRNAValue = $secondRNAInput->getAttribute('value');
            return !empty($firstRNAValue) && !empty($secondRNAValue);
        });

        // Check RNA description for first transcript.
        $firstRNAInput = $this->driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
        $firstRNAValue = $firstRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $firstRNAValue));

        // Check protein description for first transcript.
        $firstProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[1]';
        $firstProteinInput = $this->driver->findElement(WebDriverBy::xpath($firstProteinInputSelector));
        $firstProteinValue = $firstProteinInput->getAttribute('value');
        $this->assertEquals("p.(Leu21Gln)", $firstProteinValue);

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000002_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("00000003_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000004_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000005_VariantOnTranscript/Exon"), "3");

        // Check DNA description for first transcript.
        $firstDNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/DNA")])[1]';
        $firstDNAInput = $this->driver->findElement(WebDriverBy::xpath($firstDNAInputSelector));
        $firstDNAValue = $firstDNAInput->getAttribute('value');
        $this->assertEquals("c.62T>A", $firstDNAValue);

        // Check RNA description for second transcript.
        $secondRNAInput = $this->driver->findElement(WebDriverBy::xpath($secondRNAInputSelector));
        $secondRNAValue = $secondRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $secondRNAValue));

        // Check protein description for second transcript.
        $secondProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[2]';
        $secondProteinInput = $this->driver->findElement(WebDriverBy::xpath($secondProteinInputSelector));
        $secondProteinValue = $secondProteinInput->getAttribute('value');
        $this->assertEquals("p.(Leu21Gln)", $secondProteinValue);

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000003_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Maternal (confirmed)"]'));
        $option->click();
        $GenomicDNAChange = $this->driver->findElement(WebDriverBy::name('VariantOnGenome/DNA'));
        $this->assertEquals("g.2843789A>T", $GenomicDNAChange->getAttribute('value'));

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        // $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:Fokkema et al (2011):21520333}");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();

        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector("table[class=info]")));
        $this->assertContains("Successfully processed your submission and sent an email notification to the relevant curator",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
