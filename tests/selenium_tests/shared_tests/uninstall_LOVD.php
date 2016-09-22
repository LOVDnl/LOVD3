<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class UninstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testUninstallLOVDTest()
    {
        $this->logout();
        $this->login('admin', 'test1234');

        $this->driver->get(ROOT_URL . "/src/uninstall");
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Next >>']"));
        $element->click();
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Uninstall LOVD']"));
        $element->click();
        $this->assertEquals("LOVD successfully uninstalled!\nThank you for having used LOVD!", $this->driver->findElement(WebDriverBy::cssSelector("div[id=lovd__progress_message]"))->getText());
    }
}
