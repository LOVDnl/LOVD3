<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class PostFinishAddVariantOnlyDescribedOnGenomicLevelToIVAIndividualTest extends LOVDSeleniumBaseTestCase
{
    public function testPostFinishAddVariantOnlyDescribedOnGenomicLevelToIVAIndividual()
    {
        $this->open(ROOT_URL . "/src");
        $this->click("id=tab_screenings");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/IVD$/', $this->getLocation()));
        $this->click("css=#0000000002 > td.ordered");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/screenings\/0000000002$/', $this->getLocation()));
        $this->click("id=viewentryOptionsButton_Screenings");
        $this->click("link=Add variant to screening");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/', $this->getLocation()));
        $this->click("//table[2]/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Genome&target=0000000002$/', $this->getLocation()));
        $this->select("name=allele", "label=Paternal (confirmed)");
        $this->select("name=chromosome", "label=15");
        $this->type("name=VariantOnGenome/DNA", "g.40702876G>T");
        $this->click("link=PubMed");
        $this->type("name=VariantOnGenome/Reference", "{PMID:[2011]:[21520333]}");
        $this->type("name=VariantOnGenome/Frequency", "11/10000");
        $this->select("name=effect_reported", "label=Effect unknown");
        $this->select("name=effect_concluded", "label=Effect unknown");
        $this->select("name=owned_by", "label=LOVD3 Admin (#00001)");
        $this->select("name=statusid", "label=Public");
        $this->click("//input[@value='Create variant entry']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("4000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/0000000333$/', $this->getLocation()));
    }
}
