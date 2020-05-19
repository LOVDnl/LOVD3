<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantVCFFileTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantVCFFile()
    {
        $this->driver->get(ROOT_URL . "/src/variants/upload?create&type=VCF");
        $this->assertEquals('To access this area, you need at least Manager clearance.',
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
