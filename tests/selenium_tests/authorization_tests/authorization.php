<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AuthorizationTest extends LOVDSeleniumBaseTestCase
{
    public function testAuthorization()
    {
        $this->open(ROOT_URL . "/tests/unit_tests/authorization.php");
        $this->assertEquals("Complete, all successful", $this->getText("css=pre"));
    }
}
