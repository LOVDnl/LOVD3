<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantOnlyDescribedOnGenomicLevelTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantOnlyDescribedOnGenomicLevel()
    {
        $this->driver->get(ROOT_URL . "/src/variants?create&reference=Genome");
        $this->assertEquals("To access this area, you need at least Curator clearance.", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
