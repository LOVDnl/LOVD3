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
    $this->open("/svn/LOVD3/trunk/src/diseases?create");
    $this->type("name=symbol", "IVA");
    $this->type("name=name", "isovaleric acidemia");
    $this->type("name=id_omim", "243500");
    $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
    $this->click("//input[@value='Create disease information entry']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the disease information entry!", $this->getText("css=table[class=info]"));
  }
}
?>