<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class MakeUserCuratorTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testMakeUserCurator()
    {
        $this->driver->get(ROOT_URL . "/src/genes/IVD?authorize");
        $element = $this->driver->findElement(WebDriverBy::linkText("Test Curator"));
        $element->click();
        $this->enterValue(WebDriverBy::xpath("//td/input[@type='password']"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Save curator list']"));
        $element->click();
        
        $this->assertEquals("Successfully updated the curator list!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
