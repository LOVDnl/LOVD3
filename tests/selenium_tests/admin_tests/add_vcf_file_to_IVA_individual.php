<?php
require_once 'LOVDSeleniumBaseTestCase.php';

class AddVCFFileToIVAIndividualTest extends LOVDSeleniumBaseTestCase
{
    public function testAddVCFFileToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/', $this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/', $this->getLocation()));
        $this->click("//tr[3]/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000003$/', $this->getLocation()));
        $this->click("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b");
        $this->waitForPageToLoad("30000");
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=VCF&target=0000000003$/', $this->getLocation()));
        $this->type("name=variant_file", ROOT_PATH . "/tests/test_data_files/ShortVCFfilev1.vcf");
        $this->select("name=hg_build", "label=hg19");
        $this->select("name=dbSNP_column", "label=VariantOnGenome/Reference");
        $this->select("name=genotype_field", "label=Use Phred-scaled genotype likelihoods (PL)");
        $this->check("name=allow_mapping");
        $this->check("name=allow_create_genes");
        $this->select("name=owned_by", "label=LOVD3 Admin");
        $this->select("name=statusid", "label=Public");
        $this->click("//input[@value='Upload VCF file']");
        $this->waitForPageToLoad("30000");
        $this->assertEquals("25 variants were imported, 1 variant could not be imported.", $this->getText("id=lovd__progress_message"));
        $this->click("//input[@value='Continue »']");
        $this->waitForPageToLoad("30000");
        for ($second = 0; ; $second++) {
            if ($second >= 600) $this->fail("timeout");
            $this->open(ROOT_PATH . "/src/ajax/map_variants.php");
            $this->waitForPageToLoad("60000");
            if (strcmp("0 99 There are no variants to map in the database", $this->getBodyText())) {
                break;
            }
            $this->assertNotContains("of 25 variants", $this->getBodyText());
            sleep(1);
        }
    }
}
