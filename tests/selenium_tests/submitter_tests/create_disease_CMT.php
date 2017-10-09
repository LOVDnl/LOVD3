<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateDiseaseCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateDiseaseCMT()
    {
        $this->driver->get(ROOT_URL . "/src/diseases?create");
        $this->assertEquals("To access this area, you need at least Curator clearance.",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        $this->logout();
        $this->login('admin', 'test1234');

        $this->driver->get(ROOT_URL . "/src/diseases?create");
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases[\s\S]create$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("symbol"), "CMT");
        $this->enterValue(WebDriverBy::name("name"), "Charcot Marie Tooth Disease");
        $this->enterValue(WebDriverBy::name("id_omim"), "302800");
//        $this->addSelection(WebDriverBy::name("genes[]"), "value=GJB1");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genes[]"]/option[@value="GJB1"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create disease information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the disease information entry!",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());

        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("Disease"));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases\/00001$/', $this->driver->getCurrentURL()));

        $this->logout();
        $this->login('submitter', 'test1234');
    }
}
