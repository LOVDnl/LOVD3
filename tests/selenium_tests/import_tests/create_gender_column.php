<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateGenderColumnTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateGenderColumn()
    {
        $this->driver->get(ROOT_URL . "/src/columns/Individual/Gender");
        $element = $this->driver->findElement(WebDriverBy::id("viewentryOptionsButton_Columns"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("Enable column"));
        $element->click();
        
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Add/enable custom data column Individual/Gender']"));
        $element->click();
        
    }
}
