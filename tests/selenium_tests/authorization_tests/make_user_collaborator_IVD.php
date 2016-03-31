<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class MakeUserCollaboratorTest extends LOVDSeleniumBaseTestCase
{
    public function testMyTestCase()
    {
        $this->open(ROOT_URL . "/src/genes/IVD?authorize");
        $this->click("link=Test Collaborator");
        $this->click("xpath=(//input[@name='allow_edit[]'])[3]");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Save curator list']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
}
