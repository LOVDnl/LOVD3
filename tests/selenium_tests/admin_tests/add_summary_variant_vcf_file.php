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
    $this->setTimeout(60000);
    sleep(4);
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create$/',$this->getLocation()));
    $this->click("//tr[3]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->open("/svn/LOVD3/trunk/src/variants/upload?create&type=VCF");
    $this->type("name=variant_file", "/www/svn/LOVD3/trunk/tests/test_data_files/ShortVCFfilev1.vcf");
    $this->select("name=hg_build", "label=hg19");
    $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
    $this->select("name=genotype_field", "label=Use Phred-scaled genotype likelihoods (PL)");
    $this->check("name=allow_mapping");
    $this->check("name=allow_create_genes");
    $this->select("name=owned_by", "label=LOVD3 Admin");
    $this->select("name=statusid", "label=Public");
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertEquals("25 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
    $this->click("css=input[type=\"submit\"]");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/',$this->getText("css=table[class=info]")));
    $this->waitForPageToLoad("4000");
    for ($second = 0; ; $second++) {
        if ($second >= 600) $this->fail("timeout");
        $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
        $this->waitForPageToLoad("60000");
        if (strcmp("0 99 There are no variants to map in the database", $this->getBodyText())) {
            break;
        }
        $this->assertNotContains("of 25 variants", $this->getBodyText());
        sleep(1);
    }
  }
}
?>