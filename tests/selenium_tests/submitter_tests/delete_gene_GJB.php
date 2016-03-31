<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class DeleteGeneGJBTest extends LOVDSeleniumBaseTestCase
{
    public function testDeleteGeneGJB()
    {
        $this->open(ROOT_URL . "/src/genes/GJB1?delete");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
}
