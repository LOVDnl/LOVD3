<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class MakeUserCollaboratorGJBTest extends LOVDSeleniumBaseTestCase
{
    public function testMakeUserCollaboratorGJB()
    {
        $this->open(ROOT_URL . "/src/genes/GJB1?authorize");
        $this->selectWindow("null");
        $this->click("link=Test Collaborator");
        $this->uncheck("xpath=(//input[@name='allow_edit[]'])[2]");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Save curator list']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
    }
}
