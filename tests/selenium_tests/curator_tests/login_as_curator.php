<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsCuratorTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsCurator()
    {
        $this->logout();
        $this->login('curator', 'test1234');
    }
}
