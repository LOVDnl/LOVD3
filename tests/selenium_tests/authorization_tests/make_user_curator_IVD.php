<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class MakeUserCuratorTest extends LOVDSeleniumBaseTestCase
{
    public function testMakeUserCurator()
    {
        $this->open(ROOT_URL . "/src/genes/IVD?authorize");
        $this->click("link=Test Curator");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Save curator list']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
}
