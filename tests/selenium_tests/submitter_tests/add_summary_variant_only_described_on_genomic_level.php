<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantOnlyDescribedOnGenomicLevelTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantOnlyDescribedOnGenomicLevel()
    {
        $this->open(ROOT_URL . "/src/variants?create&reference=Genome");
        $this->assertEquals("To access this area, you need at least Submitter (data owner) clearance.", $this->getText("css=table[class=info]"));
    }
}
