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
    $this->open("/svn/LOVD3/trunk/src/logout");
    $this->open("/svn/LOVD3/trunk/src/login");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/login$/',$this->getLocation()));
    $this->type("name=username", "curator");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
  }
}
?>