<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSeatlleseqFileToCMTTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSeatlleseqFileToCMT()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&target=0000000002");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
}
