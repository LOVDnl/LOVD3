<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateUserSubmitterTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateUserSubmitter()
    {
        $this->driver->get(ROOT_URL . "/src/users?create&no_orcid");
        $this->enterValue(WebDriverBy::name("name"), "Test Submitter");
        $this->enterValue(WebDriverBy::name("institute"), "Leiden University Medical Center");
        $this->enterValue(WebDriverBy::name("department"), "Human Genetics");
        $this->enterValue(WebDriverBy::name("address"), "Einthovenweg 20\n2333 ZC Leiden");
        $this->enterValue(WebDriverBy::name("email"), "d.asscheman@lumc.nl");
        $this->enterValue(WebDriverBy::name("username"), "submitter");
        $this->enterValue(WebDriverBy::name("password_1"), "test1234");
        $this->enterValue(WebDriverBy::name("password_2"), "test1234");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="countryid"]/option[text()="Netherlands"]'));
        $option->click();
        $this->enterValue(WebDriverBy::name("city"), "Leiden");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="level"]/option[text()="Submitter"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::name("send_email"));
        $element->click();
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create user']"));
        $element->click();
        $this->assertEquals("Successfully created the user account!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
