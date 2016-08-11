<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AuthorizationTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAuthorization()
    {
        $this->driver->get(ROOT_URL . "/tests/unit_tests/authorization.php");
        $this->assertEquals("Complete, all successful.", $this->driver->findElement(WebDriverBy::cssSelector("pre"))->getText());
    }
}
