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
    $this->open("/svn/LOVD3/trunk/src/phenotypes?create&target=00000001");
    $this->type("name=Phenotype/Additional", "Phenotype Details");
    $this->select("name=Phenotype/Inheritance", "label=Unknown");
    $this->select("name=owned_by", "label=Test Owner");
    $this->click("//input[@value='Create phenotype information entry']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the phenotype entry!", $this->getText("css=table[class=info]"));
  }
}
?>