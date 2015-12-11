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
    $this->open("/svn/LOVD3/trunk/src/users?create&no_orcid");
    $this->type("name=name", "Test Manager");
    $this->type("name=institute", "Leiden University Medical Center");
    $this->type("name=department", "Human Genetics");
    $this->type("name=address", "Einthovenweg 20\n2333 ZC Leiden");
    $this->type("name=email", "d.asscheman@lumc.nl");
    $this->type("name=username", "manager");
    $this->type("name=password_1", "test1234");
    $this->type("name=password_2", "test1234");
    $this->select("name=countryid", "label=Netherlands");
    $this->type("name=city", "Leiden");
    $this->select("name=level", "Manager");
    $this->click("name=send_email");
    $this->type("name=password", "test1234");
    $this->click("//input[@value='Create user']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the user account!", $this->getText("css=table[class=info]"));
  }
}
?>