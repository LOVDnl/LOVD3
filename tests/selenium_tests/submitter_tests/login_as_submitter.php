<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class LoginAsSubmitterTest extends LOVDSeleniumBaseTestCase
{
    public function testLoginAsSubmitter()
    {
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/', $this->getLocation()));
        $this->type("name=username", "submitter");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");
    }
}
