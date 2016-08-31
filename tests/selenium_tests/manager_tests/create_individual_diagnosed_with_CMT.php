<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateIndividualDiagnosedWithCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateIndividualDiagnosedWithCMT()
    {
        $element = $this->driver->findElement(WebDriverBy::id("tab_submit"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("Individual/Lab_ID"), "12345CMT");
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("Individual/Reference"), "{PMID:[2011]:[21520333]}");
        $this->enterValue(WebDriverBy::name("Individual/Remarks"), "No Remarks");
        $this->enterValue(WebDriverBy::name("Individual/Remarks_Non_Public"), "Still no remarks");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_diseases[]"]/option[text()="CMT (Charcot Marie Tooth Disease)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create individual information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the individual information entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
        
    }
}
