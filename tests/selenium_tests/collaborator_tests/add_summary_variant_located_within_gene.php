<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumBaseTestCase
{
  public function testAddSummaryVariantLocatedWithinGene()
  {
    $this->open(ROOT_URL . "/src/variants?create&reference=Transcript&geneid=GJB1");
    $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
  }
}
