<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsSubmitterTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsSubmitter()
    {
        $this->logout();
        $this->login('submitter', 'test1234');
    }
}
