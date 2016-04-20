<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class CreateUserCurator2Test extends LOVDSeleniumBaseTestCase
{
    public function testCreateUserCurator2()
    {
        $this->open(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->getText("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
    }
}
