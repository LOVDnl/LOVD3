<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class UpdatetImportTest extends LOVDSeleniumBaseTestCase
{
    public function testUpdatetImport()
    {
        $this->open(ROOT_URL . "/src/import");
        $this->type("name=import", ROOT_PATH . "/tests/test_data_files/UpdateImport.txt");
        $this->select("name=mode", "label=Update existing data (in beta)");
        $this->click("//input[@value='Import file']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*The following sections are modified and updated in the database: Columns, Diseases, Individuals, Phenotypes, Screenings, Variants_On_Genome, Variants_On_Transcripts\.$/', $this->getText("id=lovd_sql_progress_message_done")));
    }
}
