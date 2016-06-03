<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateUserManager2Test extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateUserManager2()
    {
        $this->driver->get(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertNotEquals("Manager", $this->driver->findElement(WebDriverBy::name("level"))->getAttribute('value'));
    }
}
