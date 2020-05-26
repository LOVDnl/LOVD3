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

        $this->waitUntil(WebDriverExpectedCondition::urlContains('/src/individuals?create'));
        $this->enterValue(WebDriverBy::name("Individual/Lab_ID"), "12345CMT");

        // Move mouse to let browser hide tooltip of pubmed link (needed for chrome)
        // $this->driver->getMouse()->mouseMove(null, 200, 200);

        $this->enterValue(WebDriverBy::name("Individual/Reference"), "{PMID:Fokkema et al (2011):21520333}");
        $this->enterValue(WebDriverBy::name("Individual/Remarks"), "No Remarks");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_diseases[]"]/option[text()="CMT (Charcot Marie Tooth Disease)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create individual information entry']"));
        $element->click();

        $this->assertEquals("Successfully created the individual information entry!",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

    }
}
