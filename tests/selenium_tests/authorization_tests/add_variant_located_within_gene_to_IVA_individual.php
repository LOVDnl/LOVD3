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
    $this->open("/svn/LOVD3/trunk/src/variants?create&reference=Transcript&geneid=IVD&target=0000000001");
    $this->uncheck("name=ignore_00000001");
    $this->type("name=00000001_VariantOnTranscript/Exon", "2");
    $this->type("name=00000001_VariantOnTranscript/DNA", "c.345G>T");
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
    $this->assertEquals("p.(Met115Ile)", $this->getExpression($ProteinChange));
    $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
    $this->assertEquals("g.40702876G>T", $this->getExpression($GenomicDnaChange));
    $this->select("name=00000001_effect_reported", "label=Effect unknown");
    $this->select("name=00000001_effect_concluded", "label=Effect unknown");
    $this->select("name=allele", "label=Paternal (confirmed)");
    $this->click("link=PubMed");
    $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
    $this->type("name=VariantOnGenome/Frequency", "0.05");
    $this->select("name=effect_reported", "label=Effect unknown");
    $this->select("name=effect_concluded", "label=Effect unknown");
    $this->select("name=owned_by", "label=Test Owner");
    $this->select("name=statusid", "label=Public");
    $this->click("//input[@value='Create variant entry']");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("Successfully created the variant entry!", $this->getText("css=table[class=info]"));
  }
}
?>