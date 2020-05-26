<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateUserSubmitterNotAuthorizedTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateUserSubmitter()
    {
        $this->driver->get(ROOT_URL . "/src/users?create&no_orcid");
        $this->assertEquals("To access this area, you need at least Manager clearance.", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
