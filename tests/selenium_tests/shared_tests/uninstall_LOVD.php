<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class UninstallLOVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testUninstallLOVDTest()
    {
        $this->driver->get(ROOT_URL . "/src/logout");
        $this->driver->get(ROOT_URL . "/src/login");
        $this->enterValue(WebDriverBy::name("username"), "admin");
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Log in']"));
        $element->click();
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
