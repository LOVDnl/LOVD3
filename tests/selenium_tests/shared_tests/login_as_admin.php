<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class LoginAsAdminTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testLoginAsAdmin()
    {
        // FIXME: This test should most likely go.
        // It's not a test, just a requirement for other tests.
        // Besides, after installation, we're already Admin. So perhaps
        //  resorting tests should remove the necessity for this.
        // Otherwise, probably adding a setUp() with a login will suffice.
        // Checking if the "my account" link in the top right points to user 1,
        //  should suffice to check if we're the admin. If so, pass, if not,
        //  run the login() function.
        $this->logout();
        $this->login('admin', 'test1234');
    }
}
