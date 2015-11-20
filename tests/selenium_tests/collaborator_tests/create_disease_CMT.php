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
    $this->open("/svn/LOVD3/trunk/src/diseases?create");
    $this->assertEquals("To access this area, you need at least Curator clearance.", $this->getText("css=table[class=info]"));
    $this->open("/svn/LOVD3/trunk/src/logout");
    $this->open("/svn/LOVD3/trunk/src/login");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
    $this->type("name=username", "admin");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->click("link=Create a new disease information entry");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases[\s\S]create$/',$this->getLocation()));
    $this->type("name=symbol", "CMT");
    $this->type("name=name", "Charcot Marie Tooth Disease");
    $this->type("name=id_omim", "302800");
    $this->addSelection("name=genes[]", "value=GJB1");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/diseases\/00001$/',$this->getLocation()));
    $this->open("/svn/LOVD3/trunk/src/logout");
    $this->open("/svn/LOVD3/trunk/src/login");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/login$/',$this->getLocation()));
    $this->type("name=username", "collaborator");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
  }
}
?>