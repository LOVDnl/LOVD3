<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class InsertImportTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testInsertImport()
    {
        $this->driver->get(ROOT_URL . "/src/import");
        $this->enterValue(WebDriverBy::name("import"), ROOT_PATH . "/tests/test_data_files/InsertImport.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="mode"]/option[text()="Add only, treat all data as new"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Import file']"));
        $element->click();
        
        $this->assertEquals("Done importing!", $this->driver->findElement(WebDriverBy::id("lovd_sql_progress_message_done"))->getText());
    }
}
