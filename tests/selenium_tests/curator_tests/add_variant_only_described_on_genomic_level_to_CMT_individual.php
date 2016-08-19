<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddVariantOnlyDescribedOnGenomicLevelToCMTIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddVariantOnlyDescribedOnGenomicLevelToCMTIndividual()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//table[2]/tbody/tr[2]/td[2]"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000001$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Maternal (confirmed)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="chromosome"]/option[text()="X"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/DNA"), "g.70443591G>T");
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[21520333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "11/10000");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the variant entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
        
    }
}
