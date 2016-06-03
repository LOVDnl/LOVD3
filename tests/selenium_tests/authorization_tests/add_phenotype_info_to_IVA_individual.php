<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddPhenotypeInfoToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testMyTestCase()
    {
        $this->driver->get(ROOT_URL . "/src/phenotypes?create&target=00000001");
        $this->enterValue(WebDriverBy::name("Phenotype/Additional"), "Phenotype Details");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="Phenotype/Inheritance"]/option[text()="Unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="Test Owner (#00006)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create phenotype information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the phenotype entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
