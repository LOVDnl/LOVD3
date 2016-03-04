<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class SecondInsertImportTest extends LOVDSeleniumBaseTestCase
{
    public function testSecondInsertImport()
    {
        $this->open(ROOT_URL . "/src/import");
        $this->type("name=import", ROOT_PATH . "/tests/test_data_files/SecondInsertImport.txt");
        $this->select("name=mode", "label=Add only, treat all data as new");
        $this->click("//input[@value='Import file']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Done importing!", $this->getText("id=lovd_sql_progress_message_done"));
    }
}
