<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class PostFinishAddPhenotypeInfoToCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testPostFinishAddPhenotypeInfoToCMT()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Submission of"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("tab_individuals"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/GJB1$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::cssSelector("td.ordered"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Individuals"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Add phenotype information to individual"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("Phenotype/Additional"), "Additional phenotype information");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Phenotype/Inheritance"]/option[text()="Familial"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create phenotype information entry']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));

        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Phenotype"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes\/0000000002$/', $this->driver->getCurrentURL()));
    }
}
