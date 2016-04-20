<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddPhenotypeInfoToIVAIndividualTest extends LOVDSeleniumBaseTestCase
{
    public function testMyTestCase()
    {
        $this->open(ROOT_URL . "/src/phenotypes?create&target=00000001");
        $this->type("name=Phenotype/Additional", "Phenotype Details");
        $this->select("name=Phenotype/Inheritance", "label=Unknown");
        $this->select("name=owned_by", "label=Test Owner (#00006)");
        $this->click("//input[@value='Create phenotype information entry']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
    }
}
