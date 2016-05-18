<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantVCFFileTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantVCFFile()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&type=VCF");
        $this->assertEquals("To access this area, you need at least Curator clearance.", $this->getText("css=table[class=info]"));
    }
}
