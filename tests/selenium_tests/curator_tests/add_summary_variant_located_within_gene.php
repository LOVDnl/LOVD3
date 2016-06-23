<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantLocatedWithinGeneTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantLocatedWithinGene()
    {
        $this->click("id=tab_submit");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        sleep(4);
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/', $this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->click("css=#GJB1 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=GJB1$/', $this->getLocation()));
        $this->uncheck("name=ignore_00000001");
        $this->type("name=00000001_VariantOnTranscript/Exon", "3");
        $this->type("name=00000001_VariantOnTranscript/DNA", "c.62G>A");
        $this->click("css=button.mapVariant");
        sleep(3);
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent("css=img[alt=\"Prediction OK!\"]")) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Gly21Asp)", $this->getExpression($ProteinChange));
        $this->select("name=00000001_effect_reported", "label=Probably affects function");
        $this->select("name=00000001_effect_concluded", "label=Probably does not affect function");
        $this->select("name=allele", "label=Maternal (confirmed)");
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[11].value");
        $this->assertEquals("g.70443619G>A", $this->getExpression($GenomicDnaChange));
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[2150333]}");
        $this->type("name=VariantOnGenome/Frequency", "55/18000");
        $this->select("name=effect_reported", "label=Affects function");
        $this->select("name=effect_concluded", "label=Affects function");
        $this->select("name=owned_by", "label=LOVD3 Admin (#00001)");
        $this->select("name=statusid", "label=Public");
        $this->click("//input[@value='Create variant entry']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
    }
}
