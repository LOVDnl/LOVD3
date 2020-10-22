<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class MakeUserCollaboratorGJBTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testMakeUserCollaboratorGJB()
    {
        $this->driver->get(ROOT_URL . "/src/genes/GJB1?authorize");
        $element = $this->driver->findElement(WebDriverBy::linkText("Test Collaborator"));
        $element->click();
//        $this->uncheck("xpath=(//input[@name='allow_edit[]'])[2]");
        $this->uncheck(WebDriverBy::xpath("(//input[@name='allow_edit[]'])[2]"));
        $this->enterValue(WebDriverBy::xpath("//td/input[@type='password']"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Save curator list']"));
        $element->click();
        $this->assertEquals("Successfully updated the curator list!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
