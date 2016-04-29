<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddVCFFileToCMTTest extends LOVDSeleniumBaseTestCase
{
    public function testAddVCFFileToCMT()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&target=0000000002");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
}
