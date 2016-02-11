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
        $this->click("link=Submit new data");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/',$this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/',$this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->click("//tr[@id='ARSD']/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=ARSD$/',$this->getLocation()));
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent("name=ignore_00000002")) break;
            } catch (Exception $e) {}
            sleep(1);
        }
        $this->uncheck("name=ignore_00000002");
        $this->uncheck("name=ignore_00000003");
        $this->type("name=00000002_VariantOnTranscript/Exon", "3");
        $this->type("name=00000003_VariantOnTranscript/Exon", "3");
        $this->type("name=00000002_VariantOnTranscript/DNA", "c.62T>C");
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
        $this->assertEquals("p.(Leu21Pro)", $this->getExpression($ProteinChange));
        $this->select("name=00000002_effect_reported", "label=Probably affects function");
        $this->select("name=00000002_effect_concluded", "label=Probably does not affect function");
        $RnaChangeTwo = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/',$this->getExpression($RnaChangeTwo)));
        $ProteinChangeTwo = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Leu21Pro)", $this->getExpression($ProteinChangeTwo));
        $this->select("name=00000003_effect_reported", "label=Probably affects function");
        $this->select("name=00000003_effect_concluded", "label=Probably does not affect function");
        $this->select("name=allele", "label=Maternal (confirmed)");
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[19].value");
        $this->assertEquals("g.2843789A>G", $this->getExpression($GenomicDnaChange));
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "55/18000");
        $this->select("name=effect_reported", "label=Affects function");
        $this->select("name=effect_concluded", "label=Affects function");
        $this->select("name=owned_by", "label=LOVD3 Admin (#00001)");
        $this->select("name=statusid", "label=Public");
		$this->click("//input[@value='Create variant entry']");
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            if ($this->isElementPresent("css=table[class=info]")) {
                $this->assertContains("Successfully processed your submission and sent an email notification to the relevant curator", $this->getText("css=table[class=info]"));
                break;
            }
            sleep(1);
        }
        $this->waitForPageToLoad("4000");
        $this->assertContains("src/variants/0000000168", $this->getLocation());
    }
}
?>