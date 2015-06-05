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
    $this->open("/svn/LOVD3/trunk/src/columns/Individual/Gender");
    $this->click("id=viewentryOptionsButton_Columns");
    $this->click("link=Enable column");
    $this->waitForPageToLoad("30000");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
  }
}
?>