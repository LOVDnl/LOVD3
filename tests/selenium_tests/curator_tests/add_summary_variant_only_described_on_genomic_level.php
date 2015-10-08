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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000003$/',$this->getLocation()));
    $this->click("id=tab_submit");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
    $this->chooseOkOnNextConfirmation();
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
    $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
    sleep(4);
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome$/',$this->getLocation()));
    $this->select("name=allele", "label=Paternal (confirmed)");
    $this->select("name=chromosome", "label=15");
    $this->type("name=VariantOnGenome/DNA", "g.40702976G>T");
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
    $this->type("name=VariantOnGenome/Frequency", "11/10000");
    $this->select("name=effect_reported", "label=Effect unknown");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
  }
}
?>