<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class CreateUserSubmitterTest extends LOVDSeleniumBaseTestCase
{
    public function testCreateUserSubmitter()
    {
        $this->open(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("css=table[class=info]"));
    }
}
