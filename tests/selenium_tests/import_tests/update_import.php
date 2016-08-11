<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class UpdatetImportTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testUpdatetImport()
    {
        $this->driver->get(ROOT_URL . "/src/import");
        $this->enterValue(WebDriverBy::name("import"), ROOT_PATH . "/tests/test_data_files/UpdateImport.txt");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="mode"]/option[text()="Update existing data (in beta)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Import file']"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*The following sections are modified and updated in the database: Columns, Diseases, Individuals, Phenotypes, Screenings, Variants_On_Genome, Variants_On_Transcripts\.$/', $this->driver->findElement(WebDriverBy::id("lovd_sql_progress_message_done"))->getText()));
    }
}
