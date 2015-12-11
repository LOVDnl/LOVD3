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
    $this->click("id=tab_screenings");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/IVD$/',$this->getLocation()));
    $this->click("css=#0000000002 > td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/',$this->getLocation()));
    $this->click("id=viewentryOptionsButton_Screenings");
    $this->click("link=Add variant to screening");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
    $this->click("//table[2]/tbody/tr[1]/td[2]/b");
    $this->click("css=td.ordered");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000002$/',$this->getLocation()));
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
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("//input[@value='Create variant entry']");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
    $this->assertContains("/src/variants/0000000334", $this->getLocation());
  }
}
?>