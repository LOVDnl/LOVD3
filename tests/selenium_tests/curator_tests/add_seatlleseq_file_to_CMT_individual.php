<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSeatlleseqFileToCMTTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSeatlleseqFileToCMT()
    {
        $this->driver->get(ROOT_URL . "/src/variants/upload?create&target=0000000002");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]"))->getText());
    }
}
