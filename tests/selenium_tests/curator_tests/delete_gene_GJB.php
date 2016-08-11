<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class DeleteGeneGJBTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testDeleteGeneGJB()
    {
        $this->driver->get(ROOT_URL . "/src/genes/GJB1?delete");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
