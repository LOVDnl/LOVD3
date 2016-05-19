<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantSeatlleseqFileTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&type=SeattleSeq");
        $this->assertEquals("To access this area, you need at least Curator clearance.", $this->getText("css=table[class=info]"));
    }
}
