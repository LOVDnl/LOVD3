<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class MakeUserCollaboratorTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testMyTestCase()
    {
        $this->driver->get(ROOT_URL . "/src/genes/IVD?authorize");
        $element = $this->driver->findElement(WebDriverBy::linkText("Test Collaborator"));
        $element->click();
//        $element = $this->driver->findElement("xpath=(//input[@name='allow_edit[]'])[3]");
        $elements = $this->driver->findElements(WebDriverBy::xpath("//input[@name='allow_edit[]']"));
        $elements[2]->click();
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Save curator list']"));
        $element->click();
        
        $this->assertEquals("Successfully updated the curator list!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
