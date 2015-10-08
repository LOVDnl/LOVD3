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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/phenotypes\/0000000002$/',$this->getLocation()));
    $this->click("id=tab_genes");
    $this->waitForPageToLoad("30000");
    $this->click("link=GJB1");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1$/',$this->getLocation()));
    $this->click("id=viewentryOptionsButton_Genes");
    $this->click("link=Empty this gene database");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/genes\/GJB1[\s\S]empty$/',$this->getLocation()));
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully emptied the GJB1 gene database!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/configuration$/',$this->getLocation()));
  }
}
?>