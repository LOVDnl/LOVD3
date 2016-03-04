<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddVCFFileToCMTIndividualTest extends LOVDSeleniumBaseTestCase
{
    public function testAddVCFFileToCMTIndividual()
    {
        $this->open(ROOT_URL . "/src/variants/upload?create&target=0000000001");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
    }
}
