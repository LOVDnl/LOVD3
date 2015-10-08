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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/',$this->getLocation()));
    $this->click("//table[2]/tbody/tr/td[2]/b");
    $this->click("//tr[@id='IVD']/td[2]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000001$/',$this->getLocation()));
    $this->uncheck("name=ignore_00001");
    $this->type("name=00001_VariantOnTranscript/Exon", "2");
    $this->type("name=00001_VariantOnTranscript/DNA", "c.456T>G");
    $this->click("css=button.mapVariant");
    sleep(3);
    for ($second = 0; ; $second++) {
        if ($second >= 60) $this->fail("timeout");
        try {
            if ($this->isElementPresent("css=img[alt=\"Prediction OK!\"]")) break;
        } catch (Exception $e) {}
        sleep(1);
    }
    $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
    $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChange)));
    $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
    $this->assertTrue((bool)preg_match('/^p\.\(Tyr152[\s\S]*\)$/',$this->getExpression($ProteinChange)));
    $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
    $this->assertEquals("g.40702987T>G", $this->getExpression($GenomicDnaChange));
    $this->select("name=00001_effect_reported", "label=Effect unknown");
    $this->select("name=00001_effect_concluded", "label=Effect unknown");
    $this->select("name=allele", "label=Paternal (confirmed)");
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
    $this->type("name=VariantOnGenome/Frequency", "0.05");
    $this->select("name=effect_reported", "label=Effect unknown");
    $this->select("name=effect_concluded", "label=Effect unknown");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
    $this->waitForPageToLoad("4000");
  }
}
?>