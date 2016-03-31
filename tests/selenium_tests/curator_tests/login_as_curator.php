<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class LoginAsCuratorTest extends LOVDSeleniumBaseTestCase
{
    public function testLoginAsCurator()
    {
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/', $this->getLocation()));
        $this->type("name=username", "curator");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");
    }
}
