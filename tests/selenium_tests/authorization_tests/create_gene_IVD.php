<?php
class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("https://localhost/");
  }

  public function testMyTestCase()
  {
    $this->open("/svn/LOVD3/trunk/src/genes?create");
    $this->type("name=hgnc_id", "IVD");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("120000");
    $this->addSelection("name=active_transcripts[]", "label=transcript variant 1 (NM_002225.3)");
    $this->click("name=show_hgmd");
    $this->click("name=show_genecards");
    $this->click("name=show_genetests");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the gene information entry!", $this->getText("css=table[class=info]"));
  }
}
?>