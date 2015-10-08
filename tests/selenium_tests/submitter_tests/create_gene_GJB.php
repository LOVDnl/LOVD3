<?php
class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("https://localhost/svn/LOVD3/trunk/src/install/");
  }

  public function testMyTestCase()
  {
    $this->click("id=tab_genes");
    $this->waitForPageToLoad("30000");
    $this->click("link=Create a new gene entry");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes[\s\S]create$/',$this->getLocation()));
    $this->type("name=hgnc_id", "GJB1");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->addSelection("name=active_transcripts[]", "label=transcript variant 1 (NM_001097642.2)");
    $this->check("name=show_hgmd");
    $this->check("name=show_genecards");
    $this->check("name=show_genetests");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the gene information entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/',$this->getLocation()));
  }
}
?>