<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsAdminTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsAdmin()
    {
        $this->logout();
        $this->login('admin', 'test1234');
    }
}
