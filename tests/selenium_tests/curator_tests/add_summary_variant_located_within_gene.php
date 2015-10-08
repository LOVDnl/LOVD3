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
    $this->click("id=tab_submit");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
    $this->chooseOkOnNextConfirmation();
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
    $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
    sleep(4);
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->click("css=#GJB1 > td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1$/',$this->getLocation()));
    $this->uncheck("name=ignore_00001");
    $this->type("name=00001_VariantOnTranscript/Exon", "3");
    $this->type("name=00001_VariantOnTranscript/DNA", "c.62G>A");
    $this->click("css=button.mapVariant");
    sleep(10);
    $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
    $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
    $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
    $this->assertEquals("p.(Gly21Asp)", $this->getExpression($ProteinChange));
    $this->select("name=00001_effect_reported", "label=Probably affects function");
    $this->select("name=00001_effect_concluded", "label=Probably does not affect function");
    $this->select("name=allele", "label=Maternal (confirmed)");
    $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
    $this->assertEquals("g.70443619G>A", $this->getExpression($GenomicDnaChange));
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
    $this->type("name=VariantOnGenome/Frequency", "55/18000");
    $this->select("name=effect_reported", "label=Affects function");
    $this->select("name=effect_concluded", "label=Affects function");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
  }
}
?>