<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class UninstallLOVDTest extends LOVDSeleniumBaseTestCase
{
    public function testUninstallLOVDTest()
    {
        $this->open(ROOT_URL . "/src/logout");
        $this->open(ROOT_URL . "/src/login");
        $this->type("name=username", "admin");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Log in']");
        $this->waitForPageToLoad("30000");
        $this->open(ROOT_URL . "/src/uninstall");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Next >>']");
        $this->waitForPageToLoad("30000");
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Uninstall LOVD']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("LOVD successfully uninstalled!\nThank you for having used LOVD!", $this->getText("css=div[id=lovd__progress_message]"));
    }
}
