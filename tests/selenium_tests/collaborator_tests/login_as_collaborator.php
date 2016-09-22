<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsCollaboratorTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsCollaborator()
    {
        $this->logout();
        $this->login('collaborator', 'test1234');
    }
}
