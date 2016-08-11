<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantSeatlleseqFileTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->driver->get(ROOT_URL . "/src/variants/upload?create");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]"))->getText());
    }
}
