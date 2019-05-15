<?php
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
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::cssSelector("#GJB1 > td.ordered"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1$/', $this->driver->getCurrentURL()));
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "3");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.62G>A");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();

        // Wait until the first RNA-change input field contains data.
        $firstRNAInputSelector = '(//input[contains(@name, "VariantOnTranscript/RNA")])[1]';
        $this->waitUntil(function ($driver) use ($firstRNAInputSelector) {
            $firstRNAInput = $driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
            $firstRNAValue = $firstRNAInput->getAttribute('value');
            return !empty($firstRNAValue);
        });

        // Check RNA description.
        $firstRNAInput = $this->driver->findElement(WebDriverBy::xpath($firstRNAInputSelector));
        $firstRNAValue = $firstRNAInput->getAttribute('value');
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $firstRNAValue));

        // Check protein description.
        $firstProteinInputSelector = '(//input[contains(@name, "VariantOnTranscript/Protein")])[1]';
        $firstProteinInput = $this->driver->findElement(WebDriverBy::xpath($firstProteinInputSelector));
        $firstProteinValue = $firstProteinInput->getAttribute('value');
        $this->assertEquals("p.(Gly21Asp)", $firstProteinValue);

        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_reported"]/option[text()="Probably affects function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_concluded"]/option[text()="Probably does not affect function"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Maternal (confirmed)"]'));
        $option->click();
        $GenomicDnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[11].value");
        $this->assertEquals("g.70443619G>A", $GenomicDnaChange);
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:Fokkema et al (2011):21520333}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "55/18000");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        
    }
}
