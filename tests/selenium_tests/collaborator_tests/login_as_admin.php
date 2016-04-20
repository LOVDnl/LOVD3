<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class LoginAsAdminTest extends LOVDSeleniumBaseTestCase
{
    public function testLoginAsAdmin()
    {
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/', $this->getLocation()));
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");
    }
}
