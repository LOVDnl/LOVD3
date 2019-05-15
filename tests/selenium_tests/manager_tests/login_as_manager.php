<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsManagerTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsManager()
    {
        $this->logout();
        $this->login('manager', 'test1234');
    }
}
