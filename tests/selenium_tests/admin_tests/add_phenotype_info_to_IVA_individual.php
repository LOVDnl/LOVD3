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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/individual\/00000002$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes[\s\S]create&target=00000002$/',$this->getLocation()));
    $this->type("name=Phenotype/Additional", "additional information");
    $this->select("name=Phenotype/Inheritance", "label=Familial");
    $this->select("name=owned_by", "label=LOVD3 Admin (#00001)");
    $this->select("name=statusid", "label=Public");
    $this->click("//input[@value='Create phenotype information entry']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
  }
}
?>