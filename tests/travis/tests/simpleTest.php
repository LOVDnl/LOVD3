<?php

require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverExpectedCondition;

class TravisTest extends LOVDSeleniumWebdriverBaseTestCase
{

    public function testLoadPage()
    {
        $this->driver->get(ROOT_URL . "/tests/travis/simpleTest.html");
        $this->waitUntil(WebDriverExpectedCondition::titleContains('phpunit selenium test'));
    }
}
?>
