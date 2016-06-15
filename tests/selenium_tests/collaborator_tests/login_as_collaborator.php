<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class LoginAsCollaboratorTest extends LOVDSeleniumBaseTestCase
{
    public function testLoginAsCollaborator()
    {
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/', $this->getLocation()));
        $this->type("name=username", "collaborator");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");
    }
}
