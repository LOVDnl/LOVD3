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
    $this->open("/svn/LOVD3/trunk/src/submit");
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/individuals[\s\S]create$/',$this->getLocation()));
    $this->type("name=Individual/Lab_ID", "12345IVA");
    $this->click("link=PubMed");
    $this->type("name=Individual/Reference", "{PMID:[2011]:[21520333]}");
    $this->type("name=Individual/Remarks", "No Remarks");
    $this->type("name=Individual/Remarks_Non_Public", "Still no remarks");
    $this->addSelection("name=active_diseases[]", "label=IVA (isovaleric acidemia)");
    $this->select("name=owned_by", "label=Test Owner (#00006)");
    $this->select("name=statusid", "label=Public");
    $this->click("//input[@value='Create individual information entry']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the individual information entry!", $this->getText("css=table[class=info]"));
  }
}
?>