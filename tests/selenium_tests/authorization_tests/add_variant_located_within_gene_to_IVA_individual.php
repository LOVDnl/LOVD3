<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddVariantLocatedWithinGeneToIVAIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddVariantLocatedWithinGeneToIVAIndividual()
    {
        $this->driver->get(ROOT_URL . "/src/variants?create&reference=Transcript&geneid=IVD&target=0000000001");
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "2");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.345G>T");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        $element->click();
        sleep(3);
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::cssSelector("img[alt='Prediction OK!']"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $RnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $RnaChange));
        $ProteinChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[5].value");
        $this->assertEquals("p.(Met115Ile)", $ProteinChange);
        $GenomicDnaChange = $this->driver->executeScript("return window.document.getElementById('variantForm').elements[11].value");
        $this->assertEquals("g.40702876G>T", $GenomicDnaChange);
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="00000001_effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="allele"]/option[text()="Paternal (confirmed)"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::linkText("PubMed"));
        $element->click();
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Reference"), "{PMID:[2011]:[2150333]}");
        $this->enterValue(WebDriverBy::name("VariantOnGenome/Frequency"), "0.05");
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_reported"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="effect_concluded"]/option[text()="Effect unknown"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="Test Owner (#00006)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        
        $this->assertEquals("Successfully created the variant entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
