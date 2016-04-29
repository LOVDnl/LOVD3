<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantSeatlleseqFileTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
    }
}
