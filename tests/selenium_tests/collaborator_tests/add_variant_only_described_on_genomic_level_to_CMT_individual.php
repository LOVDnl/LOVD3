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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
    $this->click("//table[2]/tbody/tr[2]/td[2]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000001$/',$this->getLocation()));
    $this->select("name=allele", "label=Maternal (confirmed)");
    $this->select("name=chromosome", "label=X");
    $this->type("name=VariantOnGenome/DNA", "g.70443591G>T");
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
    $this->type("name=VariantOnGenome/Frequency", "11/10000");
    $this->select("name=effect_reported", "label=Effect unknown");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
  }
}
?>