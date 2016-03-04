<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSeatlleseqFileToCMTIndividualTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSeatlleseqFileToCMTIndividual()
    {
        $this->open(ROOT_URL . "/src/submit/screening/0000000002");
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000002$/', $this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000002$/', $this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq&target=0000000002$/', $this->getLocation()));
        $this->type("name=variant_file", ROOT_PATH . "/tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=autocreate", "label=Create genes and transcripts");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("//input[@value='Upload SeattleSeq file']");
        for ($second = 0; ; $second++) {
            if ($second >= 300) $this->fail("timeout");
            try {
                if ($this->isElementPresent("//input[@value='Continue »']")) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }

        $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("//input[@value='Continue »']");
        $this->waitForPageToLoad("30000");
    }
}
