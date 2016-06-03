<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddSummaryVariantVCFFileTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddSummaryVariantVCFFile()
    {
        // Wait for redirect
        $this->waitUntil(WebDriverExpectedCondition::titleContains("View genomic variant"));

        $element = $this->driver->findElement(WebDriverBy::id("tab_submit"));
        $element->click();
        
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr[2]/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*Please reconsider to submit individual data as well, as it makes the data you submit much more valuable![\s\S]*$/', $this->getConfirmation()));
        $this->chooseOkOnNextConfirmation();
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[3]/td[2]"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]"));
        $element->click();
        
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants\/upload[\s\S]create&type=VCF$/', $this->driver->getCurrentURL()));
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
        
        $this->assertTrue((bool)preg_match('/^Successfully processed your submission and sent an email notification to the relevant curator[\s\S]*$/', $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText()));
        
        for ($second = 0; ; $second++) {
            if ($second >= 600) $this->fail("timeout");
            $this->driver->get(ROOT_URL . "/src/ajax/map_variants.php");
            
            if (strcmp("0 99 There are no variants to map in the database", $this->driver->findElement(WebDriverBy::tagName("body"))->getText())) {
                break;
            }
            $this->assertNotContains("of 25 variants", $this->driver->findElement(WebDriverBy::tagName("body"))->getText());
            sleep(1);
        }
    }
}
