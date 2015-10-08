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
    $this->click("id=tab_individuals");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/IVD$/',$this->getLocation()));
    $this->click("css=#00000002 > td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals\/00000002$/',$this->getLocation()));
    $this->click("id=viewentryOptionsButton_Individuals");
    $this->click("link=Add screening to individual");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings[\s\S]create&target=00000002$/',$this->getLocation()));
    $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
    $this->addSelection("name=Screening/Template[]", "label=Protein");
    $this->addSelection("name=Screening/Technique[]", "label=array for Comparative Genomic Hybridisation");
    $this->addSelection("name=Screening/Technique[]", "label=array for resequencing");
    $this->addSelection("name=Screening/Technique[]", "label=array for SNP typing");
    $this->addSelection("name=genes[]", "label=IVD (isovaleryl-CoA dehydrogenase)");
    $this->check("name=variants_found");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000004$/',$this->getLocation()));
  }
}
?>