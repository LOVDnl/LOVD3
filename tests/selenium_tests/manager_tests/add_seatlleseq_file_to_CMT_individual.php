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
    $this->open("/svn/LOVD3/trunk/src/submit/screening/0000000002");
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants[\s\S]create&target=0000000002$/',$this->getLocation()));
    $this->click("//tr[3]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/upload[\s\S]create&target=0000000002$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/upload[\s\S]create&type=SeattleSeq&target=0000000002$/',$this->getLocation()));
    $this->type("name=variant_file", "/www/svn/LOVD3/trunk/tests/test_data_files/ShortSeattleSeqAnnotation138v1.txt");
    $this->select("name=hg_build", "label=hg19");
    $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
    $this->select("name=autocreate", "label=Create genes and transcripts");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    // Importing seatlleseq can take some time, therefore the timeout is extended. And seet back to 3000 after seatlleseq is successfully imported.
    $this->setTimeout(100000)
    for ($second = 0; ; $second++) {
        if ($second >= 60) $this->fail("timeout");
        try {
            if ($this->isElementPresent("css=input[type=\"button\"]")) break;
        } catch (Exception $e) {}
        sleep(1);
    }

    $this->setTimeout(3000)
    $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
    $this->click("css=input[type=\"button\"]");
    $this->waitForPageToLoad("30000");
  }
}
?>