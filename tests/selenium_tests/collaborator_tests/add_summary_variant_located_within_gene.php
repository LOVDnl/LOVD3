<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumWebdriverBaseTestCase
{
  public function testAddSummaryVariantLocatedWithinGene()
  {
    $this->driver->get(ROOT_URL . "/src/variants?create&reference=Transcript&geneid=GJB1");
    $this->assertEquals("To access this area, you need at least Curator clearance.", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
  }
}
