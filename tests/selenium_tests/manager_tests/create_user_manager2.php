<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class CreateUserManager2Test extends LOVDSeleniumBaseTestCase
{
    public function testCreateUserManager2()
    {
        $this->open(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertNotEquals("Manager", $this->getSelectedLabel("name=level"));
    }
}
