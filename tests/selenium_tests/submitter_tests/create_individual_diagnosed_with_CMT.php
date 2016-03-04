<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class CreateIndividualDiagnosedWithCMTTest extends LOVDSeleniumBaseTestCase
{
    public function testCreateIndividualDiagnosedWithCMT()
    {
        $this->click("id=tab_submit");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/', $this->getLocation()));
        $this->type("name=Individual/Lab_ID", "12345CMT");
        $this->click("link=PubMed");
        $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=Individual/Remarks", "No Remarks");
        $this->addSelection("name=active_diseases[]", "label=CMT (Charcot Marie Tooth Disease)");
        $this->click("//input[@value='Create individual information entry']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
    }
}
