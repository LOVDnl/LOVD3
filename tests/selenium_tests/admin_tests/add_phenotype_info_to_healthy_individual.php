<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddPhenotypeInfoToHealthyIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddPhenotypeInfoToHealthyIndividual()
    {
        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b")));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000001$/', $this->driver->getCurrentURL()));

        // This page redirects once. To prevent problems, we'll wait until we get the form.
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//input[@value='Create phenotype information entry']")));

        $this->enterValue(WebDriverBy::name("Phenotype/Age"), "35y");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create phenotype information entry']"));
        $element->click();
        $this->assertEquals("Successfully created the phenotype entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
