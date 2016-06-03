<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddPhenotypeInfoToCMTIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddPhenotypeInfoToCMTIndividual()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("Phenotype/Additional"), "Additional phenotype information");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Phenotype/Inheritance"]/option[text()="Familial"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create phenotype information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the phenotype entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
        
    }
}
