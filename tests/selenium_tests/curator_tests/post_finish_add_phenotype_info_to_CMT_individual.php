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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/submit\/screening\/0000000003$/',$this->getLocation()));
    $this->click("id=tab_individuals");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/individuals$/',$this->getLocation()));
    $this->click("css=td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/individuals\/00000001$/',$this->getLocation()));
    $this->click("id=viewentryOptionsButton_Individuals");
    $this->click("link=Add phenotype information to individual");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/phenotypes[\s\S]create&target=00000001$/',$this->getLocation()));
    $this->type("name=Phenotype/Additional", "Additional phenotype information");
    $this->select("name=Phenotype/Inheritance", "label=Familial");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/phenotypes\/0000000002$/',$this->getLocation()));
  }
}
?>