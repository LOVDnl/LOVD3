<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddScreeningToCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddScreeningToCMT()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Genomic variant"));

        $this->assertContains("/src/variants/0000000332", $this->driver->getCurrentURL());
        $element = $this->driver->findElement(WebDriverBy::id("tab_individuals"));
        $element->click();

        // This sometimes fails on Travis. Didn't try locally. On the screenshot I see we're still at the previous page (variants/332).
        // The individuals tab is visible in the screenshot.
        // Add a waitUntil() might help, but seriously, the code above shouldn't be here.
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/GJB1$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::cssSelector("td.ordered"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Individuals"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add screening to individual"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000001$/', $this->driver->getCurrentURL()));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="RNA (cDNA)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Template[]"]/option[text()="Protein"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for Comparative Genomic Hybridisation"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for resequencing"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Screening/Technique[]"]/option[text()="array for SNP typing"]'));
        $option->click();
//        $this->addSelection(WebDriverBy::name("genes[]"), "value=GJB1");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genes[]"]/option[@value="GJB1"]'));
        $option->click();
        $this->check(WebDriverBy::name("variants_found"));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create screening information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the screening entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
        
    }
}
