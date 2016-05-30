<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddVCFFileToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddVCFFileToIVAIndividual()
    {
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&target=0000000003$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=VCF&target=0000000003$/', $this->driver->getCurrentURL()));
        $this->enterValue(WebDriverBy::name("variant_file"), ROOT_PATH . "/tests/test_data_files/ShortVCFfilev1.vcf");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="hg_build"]/option[text()="hg19"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="dbSNP_column"]/option[text()="VariantOnGenome/Reference"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="genotype_field"]/option[text()="Use Phred-scaled genotype likelihoods (PL)"]'));
        $option->click();
        $this->check(WebDriverBy::name("allow_mapping"));
        $this->check(WebDriverBy::name("allow_create_genes"));
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Upload VCF file']"));
        $element->click();
        $this->assertEquals("25 variants were imported, 1 variant could not be imported.", $this->driver->findElement(WebDriverBy::id("lovd__progress_message"))->getText());
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Continue »']"));
        $element->click();
        for ($second = 0; ; $second++) {
            if ($second >= 600) $this->fail("timeout");
            $this->driver->get(ROOT_PATH . "/src/ajax/map_variants.php");
            $element->click();
            if (strcmp("0 99 There are no variants to map in the database", $this->getBodyText())) {
                break;
            }
            $this->assertNotContains("of 25 variants", $this->getBodyText());
            sleep(1);
        }
    }
}
