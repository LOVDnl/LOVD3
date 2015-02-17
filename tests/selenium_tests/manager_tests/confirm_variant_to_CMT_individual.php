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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
    $this->chooseOkOnNextConfirmation();
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]");
    $this->assertTrue((bool)preg_match('/^[\s\S]*Are you sure you are done with submitting the variants found with this screening[\s\S][\s\S]*$/',$this->getConfirmation()));
    sleep(4);
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/submit\/individual\/00000001$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/screenings[\s\S]create&target=00000001$/',$this->getLocation()));
    $this->addSelection("name=Screening/Template[]", "label=RNA (cDNA)");
    $this->addSelection("name=Screening/Template[]", "label=Protein");
    $this->addSelection("name=Screening/Technique[]", "label=Single Base Extension");
    $this->addSelection("name=Screening/Technique[]", "label=Single-Strand DNA Conformation polymorphism Analysis (SSCP)");
    $this->addSelection("name=Screening/Technique[]", "label=SSCA, fluorescent (SSCP)");
    $this->addSelection("name=genes[]", "label=GJB1 (gap junction protein, beta 1, 32kDa)");
    $this->check("name=variants_found");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the screening entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/submit\/screening\/0000000002$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/screenings\/0000000002[\s\S]confirmVariants$/',$this->getLocation()));
    $this->click("id=check_0000000001");
    $this->type("name=password", "test1234");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully confirmed the variant entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
  }
}
?>