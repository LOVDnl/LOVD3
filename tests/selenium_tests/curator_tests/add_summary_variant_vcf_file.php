<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantVCFFileTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantVCFFile()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&type=VCF");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
    }
}
