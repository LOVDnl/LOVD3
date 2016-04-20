<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddSummaryVariantSeatlleseqFileTest extends LOVDSeleniumBaseTestCase
{
    public function testAddSummaryVariantSeatlleseqFile()
    {
        $this->assertContains("/src/variants/0000000167", $this->getLocation());
        $this->click("id=tab_submit");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit$/', $this->getLocation()));
        $this->chooseOkOnNextConfirmation();
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        sleep(4);
        $this->click("//tr[3]/td[2]");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/', $this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=SeattleSeq$/', $this->getLocation()));
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

        $this->assertContains("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("//input[@value='Continue »']");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->getText("css=table[class=info]")));
        $this->waitForPageToLoad("10000");
    }
}
