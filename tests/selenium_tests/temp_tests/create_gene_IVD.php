<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class CreateGeneIVDTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testCreateGeneIVD()
    {
        $this->driver->get(ROOT_URL . "/src/logout");

        // Wait for logout to complete. Unfortunately we don't know where
        // logout will redirect us to, so we cannot explicitly wait until
        // an element is present on the page. Therefore we resort to sleeping
        // for a while.
        sleep(SELENIUM_TEST_SLEEP);

        $this->driver->get(ROOT_URL . "/src/login");
        $this->enterValue(WebDriverBy::name("username"), "admin");
        $this->enterValue(WebDriverBy::name("password"), "test1234");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Log in']"));
        $element->click();
        
        $this->driver->get(ROOT_URL . "/src/genes?create");
        $this->enterValue(WebDriverBy::name("hgnc_id"), "IVD");
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="active_transcripts[]"]/option[text()="transcript variant 1 (NM_002225.3)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_hgmd"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_genecards"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::name("show_genetests"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create gene information entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the gene information entry!",
            $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
