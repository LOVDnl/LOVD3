<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class DeleteGeneIVDTest extends LOVDSeleniumBaseTestCase
{
    public function testDeleteGeneIVD()
    {
        $this->open(ROOT_URL . "/src/phenotypes/0000000003");
        $this->click("id=tab_genes");
        $this->waitForPageToLoad("30000");
        $this->click("id=viewentryOptionsButton_Genes");
        $this->click("link=Delete gene entry");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/IVD[\s\S]delete$/', $this->getLocation()));
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Delete gene information entry']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("You are about to delete 1 transcript(s) and related information on 3 variant(s) on those transcripts. Please fill in your password one more time to confirm the removal of gene IVD", $this->getText("//*/table[@class=\"info\"][2]"));
        $this->type("name=password", "test1234");
        $this->click("//input[@value='Delete gene information entry']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully deleted the gene information entry!", $this->getText("css=table[class=info]"));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes$/', $this->getLocation()));
    }
}
