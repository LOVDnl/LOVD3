<?php
require_once 'LOVDSeleniumBaseTestCase.php';

use \Facebook\WebDriver\WebDriverBy;
use \Facebook\WebDriver\WebDriverExpectedCondition;

class AddVariantLocatedWithinGeneToHealthyIndividualTest extends LOVDSeleniumWebdriverBaseTestCase
{
    public function testAddVariantLocatedWithinGeneToHealthyIndividual()
    {
        // wait for page redirect
        $this->waitUntil(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b")));

        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/submit\/screening\/0000000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//div/table/tbody/tr/td/table/tbody/tr/td[2]/b"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&target=0000000001$/', $this->driver->getCurrentURL()));
        $element = $this->driver->findElement(WebDriverBy::xpath("//table[2]/tbody/tr/td[2]/b"));
        $element->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//tr[@id='IVD']/td[2]"));
        $element->click();
        $this->assertTrue((bool)preg_match('/^[\s\S]*\/src\/variants[\s\S]create&reference=Transcript&geneid=IVD&target=0000000001$/', $this->driver->getCurrentURL()));
        $this->uncheck(WebDriverBy::name("ignore_00000001"));
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/Exon"), "2");
        $this->enterValue(WebDriverBy::name("00000001_VariantOnTranscript/DNA"), "c.456T>G");
        $element = $this->driver->findElement(WebDriverBy::cssSelector("button.mapVariant"));
        sleep(3);
        for ($second = 0; ; $second++) {
            if ($second >= 60) $this->fail("timeout");
            try {
                if ($this->isElementPresent(WebDriverBy::cssSelector("img[alt='Prediction OK!']"))) break;
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $RnaChange = $this->getEval("window.document.getElementById('variantForm').elements[4].value");
        $this->assertTrue((bool)preg_match('/^r\.\([\s\S]\)$/', $this->getExpression($RnaChange)));
        $ProteinChange = $this->getEval("window.document.getElementById('variantForm').elements[5].value");
        $this->assertTrue((bool)preg_match('/^p\.\(Tyr152[\s\S]*\)$/', $this->getExpression($ProteinChange)));
        $GenomicDnaChange = $this->getEval("window.document.getElementById('variantForm').elements[10].value");
        $this->assertEquals("g.40702987T>G", $this->getExpression($GenomicDnaChange));
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
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="owned_by"]/option[text()="LOVD3 Admin (#00001)"]'));
        $option->click();
        $option = $this->driver->findElement(WebDriverBy::xpath('//select[@name="statusid"]/option[text()="Public"]'));
        $option->click();
        $element = $this->driver->findElement(WebDriverBy::xpath("//input[@value='Create variant entry']"));
        $element->click();
        $this->assertEquals("Successfully created the variant entry!", $this->driver->findElement(WebDriverBy::cssSelector("table[class=info]"))->getText());
    }
}
