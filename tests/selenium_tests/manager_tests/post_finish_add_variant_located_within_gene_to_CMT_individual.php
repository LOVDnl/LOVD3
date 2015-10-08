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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000557$/',$this->getLocation()));
    $this->click("id=tab_screenings");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/GJB1$/',$this->getLocation()));
    $this->click("css=#0000000002 > td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/',$this->getLocation()));
    $this->click("id=viewentryOptionsButton_Screenings");
    $this->click("link=Add variant to screening");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
    $this->click("//table[2]/tbody/tr/td[2]/b");
    $this->click("css=td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1&target=0000000002$/',$this->getLocation()));
    $this->uncheck("name=ignore_00001");
    $this->type("name=00001_VariantOnTranscript/Exon", "2");
    $this->type("name=00001_VariantOnTranscript/DNA", "c.251T>A");
    $this->click("css=button.mapVariant");
    sleep(10);
    $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
    $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
    $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
    $this->assertEquals("p.(Val84Asp)", $this->getExpression($ProteinChange));
    $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
    $this->assertEquals("g.70443808T>A", $this->getExpression($GenomicDnaChange));
    $this->select("name=00001_effect_reported", "label=Effect unknown");
    $this->select("name=00001_effect_concluded", "label=Effect unknown");
    $this->select("name=allele", "label=Paternal (confirmed)");
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
    $this->type("name=VariantOnGenome/Frequency", "0.09");
    $this->select("name=effect_reported", "label=Effect unknown");
    $this->select("name=effect_concluded", "label=Effect unknown");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
  }
}
?>