<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateUserCurator2Test extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateUserCurator2()
    {
        $this->driver->get(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]"))->getText());
    }
}
