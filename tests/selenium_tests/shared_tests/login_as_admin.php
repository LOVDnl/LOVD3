<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsAdminTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsAdmin()
    {
        $this->driver->get(ROOT_URL . "/src/logout");

        // Wait for logout to complete. Unfortunately we don't know where
        // logout will redirect us to, so we cannot explicitly wait until
        // an element is present on the page. Therefore we resort to sleeping
        // for a while.
        sleep(SELENIUM_TEST_SLEEP);

        $this->driver->get(ROOT_URL . "/src/login");
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name("username")));
        $this->enterValue(WebDriverBy::name("username"), "admin");
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Log in']"));
        $element->click();
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//b[text()="Welcome, LOVD3 Admin"]')));
    }
}