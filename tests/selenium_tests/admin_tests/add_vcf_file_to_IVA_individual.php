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
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/submit\/screening\/0000000003$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants[\s\S]create&target=0000000003$/',$this->getLocation()));
    $this->click("//tr[3]/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/upload[\s\S]create&target=0000000003$/',$this->getLocation()));
    $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
    $this->waitForPageToLoad("30000");
    $this->assertTrue((bool)preg_match('/^[\s\S]*\/trunk\/src\/variants\/upload[\s\S]create&type=VCF&target=0000000003$/',$this->getLocation()));
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
    $this->assertEquals("138 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
    $this->click("css=input[type=\"button\"]");
    $this->waitForPageToLoad("30000");
    sleep(200);
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->open("/svn/LOVD3/trunk/src/ajax/map_variants.php");
    $this->assertEquals("0 99 There are no variants to map in the database", $this->getText("css=body"));
  }
}
?>