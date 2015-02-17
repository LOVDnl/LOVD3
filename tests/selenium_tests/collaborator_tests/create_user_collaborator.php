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
    $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
    $this->type("name=name", "Test Collaborator");
    $this->type("name=institute", "Leiden University Medical Center");
    $this->type("name=department", "Human Genetics");
    $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
    $this->type("name=email", "I.F.A.C.Fokkema@LUMC.nl");
    $this->type("name=username", "collaborator");
    $this->type("name=password_1", "test1234");
    $this->type("name=password_2", "test1234");
    $this->select("name=countryid", "label=Netherlands");
    $this->type("name=city", "Leiden");
    $this->select("name=level", "Submitter");
    $this->click("name=send_email");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
  }
}
?>