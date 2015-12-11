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
    $this->open("/svn/LOVD3/trunk/src/genes/IVD?authorize");
    $this->click("link=Test Collaborator");
    $this->click("xpath=(//input[@name='allow_edit[]'])[3]");
    $this->type("name=password", "test1234");
    $this->click("//input[@value='Save curator list']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully updated the curator list!", $this->getText("css=table[class=info]"));
  }
}
?>